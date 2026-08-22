"""
GeoTIFF reading + 224x224 tiling.

Keeps each tile's pixel-space origin and geographic bounds (derived from the
raster's affine transform) so tiles can be traced back to a location later.

Orthomosaics from photogrammetry tools are usually an irregular/rotated
footprint padded out to a rectangle, so a chunk of the raster is
nodata/transparent padding rather than real imagery. Tiles that are mostly
nodata are dropped so they don't get fed into feature extraction and
clustered as if they were a real land-cover class.
"""
from __future__ import annotations

from dataclasses import dataclass

import cv2
import numpy as np
import rasterio
from rasterio.transform import Affine


@dataclass(frozen=True)
class RasterInfo:
    crs: str
    transform: Affine
    width: int
    height: int
    band_count: int


@dataclass(frozen=True)
class Tile:
    tile_id: int
    pixel_row: int
    pixel_col: int
    native_height: int  # pre-resize height, in source pixels (< tile_size at raster edges)
    native_width: int
    image: np.ndarray  # tile_size x tile_size x 3, uint8, RGB
    geo_bounds: tuple[float, float, float, float]  # (min_x, min_y, max_x, max_y)
    nodata_fraction: float  # share of the tile's pixels that were nodata, before any resize


def _to_uint8(band_data: np.ndarray) -> np.ndarray:
    """Rescale arbitrary-dtype raster data (uint16, float reflectance, ...) to uint8.

    Orthomosaics are frequently not 8-bit, but ResNet/ImageNet normalization and
    PNG export both assume it. A 2nd/98th percentile stretch avoids a few
    outlier pixels crushing the rest of the scene to black/white.
    """
    if band_data.dtype == np.uint8:
        return band_data
    data = band_data.astype(np.float32)
    lo, hi = np.percentile(data, [2, 98])
    if hi <= lo:
        hi = lo + 1.0
    scaled = np.clip((data - lo) / (hi - lo), 0.0, 1.0) * 255.0
    return scaled.astype(np.uint8)


def read_geotiff(path: str) -> tuple[np.ndarray, np.ndarray, RasterInfo]:
    """Returns (rgb_image, validity_mask, info). validity_mask is HxW, 255=valid pixel, 0=nodata."""
    with rasterio.open(path) as src:
        info = RasterInfo(
            crs=str(src.crs) if src.crs else "unknown",
            transform=src.transform,
            width=src.width,
            height=src.height,
            band_count=src.count,
        )
        band_count = min(src.count, 3)
        raw = src.read(list(range(1, band_count + 1)))  # (bands, H, W)
        # dataset_mask() folds together the alpha band, a per-dataset mask
        # band, or a declared nodata value -- whichever the file actually uses.
        mask = src.dataset_mask()

    image = np.moveaxis(raw, 0, -1)  # H, W, bands
    image = _to_uint8(image)
    if image.shape[-1] < 3:
        image = np.repeat(image[..., :1], 3, axis=-1)
    return image[..., :3], mask, info


def tile_raster(
    image: np.ndarray,
    mask: np.ndarray,
    info: RasterInfo,
    tile_size: int = 224,
    max_nodata_fraction: float = 0.3,
) -> list[Tile]:
    """Tiles are dropped outright if more than max_nodata_fraction of their pixels are nodata."""
    height, width = image.shape[:2]
    tiles: list[Tile] = []
    tile_id = 0
    for row0 in range(0, height, tile_size):
        for col0 in range(0, width, tile_size):
            row1 = min(row0 + tile_size, height)
            col1 = min(col0 + tile_size, width)

            mask_patch = mask[row0:row1, col0:col1]
            nodata_fraction = float((mask_patch == 0).mean())
            if nodata_fraction > max_nodata_fraction:
                continue

            patch = image[row0:row1, col0:col1]
            native_height, native_width = patch.shape[:2]
            if (native_height, native_width) != (tile_size, tile_size):
                # Edge tile smaller than tile_size: resize up to the ResNet
                # input size with OpenCV rather than dropping it.
                patch = cv2.resize(patch, (tile_size, tile_size), interpolation=cv2.INTER_LINEAR)

            x0, y0 = info.transform * (col0, row0)
            x1, y1 = info.transform * (col1, row1)
            geo_bounds = (min(x0, x1), min(y0, y1), max(x0, x1), max(y0, y1))

            tiles.append(
                Tile(
                    tile_id=tile_id,
                    pixel_row=row0,
                    pixel_col=col0,
                    native_height=native_height,
                    native_width=native_width,
                    image=patch,
                    geo_bounds=geo_bounds,
                    nodata_fraction=nodata_fraction,
                )
            )
            tile_id += 1
    return tiles
