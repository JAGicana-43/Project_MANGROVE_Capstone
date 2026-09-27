"""Generates a downscaled PNG preview of a GeoTIFF orthomosaic for map
display, so the browser doesn't have to download and parse the full
multi-hundred-MB source file just to show it on screen.

Real problem this fixes, not a hypothetical: a real orthomosaic in this
project's storage is 131MB, and the workspace map was downloading and
parsing that entire raw GeoTIFF client-side (via geotiff.js/parseGeoraster)
on every single page load, every time the Orthomosaic or Sampling Points
route was opened.

Reads at a decimated resolution directly via rasterio's out_shape (not "read
the whole file, then downscale in memory"), so this stays fast and
memory-light even on a large source file -- rasterio only decodes the
pyramid/pixels needed for the requested output size. Output is a plain RGBA
PNG (nodata made transparent) meant to be displayed with Leaflet's
L.imageOverlay() against the orthomosaic's already-stored
bounds_coordinates -- the same pattern already used for the suitability
overlay -- instead of GeoRasterLayer/parseGeoraster, which is what actually
eliminates the client-side parsing cost, not just the download size.

Color handling (percentile stretch, band selection) intentionally mirrors
tiling.py's read_geotiff()/_to_uint8() so the preview looks like the same
image the classification pipeline actually sees, not a differently-toned
one.

Usage: generate_web_preview.py <input.tif> <output.png> [--max-dimension N]
"""
from __future__ import annotations

import argparse
from pathlib import Path

import cv2
import numpy as np
import rasterio
from rasterio.enums import Resampling


def generate_preview(input_path: str, output_path: str, max_dimension: int = 2000) -> None:
    with rasterio.open(input_path) as src:
        scale = min(1.0, max_dimension / max(src.width, src.height))
        out_height = max(1, round(src.height * scale))
        out_width = max(1, round(src.width * scale))

        band_count = min(src.count, 3)
        raw = src.read(
            list(range(1, band_count + 1)),
            out_shape=(band_count, out_height, out_width),
            resampling=Resampling.average,
        )
        # dataset_mask()'s decimated equivalent -- nearest-neighbor since a
        # mask is binary-ish (valid/nodata), not a value that should blend
        # at edges the way average-resampled color data should.
        mask = src.read_masks(1, out_shape=(out_height, out_width), resampling=Resampling.nearest)

    image = np.moveaxis(raw, 0, -1)  # H, W, bands

    if image.dtype != np.uint8:
        data = image.astype(np.float32)
        lo, hi = np.percentile(data, [2, 98])
        if hi <= lo:
            hi = lo + 1.0
        image = np.clip((data - lo) / (hi - lo), 0.0, 1.0) * 255.0
        image = image.astype(np.uint8)

    if image.shape[-1] < 3:
        image = np.repeat(image[..., :1], 3, axis=-1)
    rgb = image[..., :3]

    rgba = np.dstack([rgb, mask]).astype(np.uint8)
    bgra = cv2.cvtColor(rgba, cv2.COLOR_RGBA2BGRA)

    Path(output_path).parent.mkdir(parents=True, exist_ok=True)
    # Max PNG compression (9) -- this runs once at upload time, not on every
    # page view, so the extra encode time is a non-issue; the resulting file
    # gets downloaded by the browser on every workspace page load, where
    # smaller genuinely matters.
    cv2.imwrite(output_path, bgra, [cv2.IMWRITE_PNG_COMPRESSION, 9])


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("input_path")
    parser.add_argument("output_path")
    parser.add_argument("--max-dimension", type=int, default=2000)
    args = parser.parse_args()

    generate_preview(args.input_path, args.output_path, args.max_dimension)
    print(f"Wrote preview to {args.output_path}")


if __name__ == "__main__":
    main()
