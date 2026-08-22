"""Reconstructs a flat-color cluster map at the source orthomosaic's pixel grid.

Lets you visually confirm cluster coverage spans the full valid footprint,
rather than trusting the per-cluster tile counts alone.
"""
from __future__ import annotations

from pathlib import Path

import cv2
import numpy as np

from .tiling import Tile

# Fixed, visually distinct RGB palette; cycles if k exceeds its length.
# 10 entries to cover the default k-sweep range (k=4..10) without repeats.
PALETTE = [
    (230, 25, 75),   # red
    (60, 180, 75),   # green
    (255, 225, 25),  # yellow
    (0, 130, 200),   # blue
    (245, 130, 48),  # orange
    (145, 30, 180),  # purple
    (70, 240, 240),  # cyan
    (240, 50, 230),  # magenta
    (210, 245, 60),  # lime
    (128, 128, 0),   # olive
]
UNPROCESSED_COLOR = (60, 60, 60)  # dark gray: dropped/nodata tile, not a real cluster


def cluster_color(label: int) -> tuple[int, int, int]:
    return PALETTE[label % len(PALETTE)]


def build_cluster_map(tiles: list[Tile], labels: np.ndarray, image_height: int, image_width: int) -> np.ndarray:
    """RGB canvas at the source raster's full pixel grid. Tiles dropped for exceeding
    the nodata threshold are never written, so they stay UNPROCESSED_COLOR -- any such
    patch stands out clearly against the flat cluster colors around it."""
    canvas = np.full((image_height, image_width, 3), UNPROCESSED_COLOR, dtype=np.uint8)
    for tile, label in zip(tiles, labels):
        row1 = tile.pixel_row + tile.native_height
        col1 = tile.pixel_col + tile.native_width
        canvas[tile.pixel_row:row1, tile.pixel_col:col1] = cluster_color(int(label))
    return canvas


def draw_legend_canvas(rows: list[tuple[tuple[int, int, int], str]], width: int, warning: str | None = None) -> np.ndarray:
    """rows: [(color_rgb, text), ...], one swatch+line per entry.

    Returned array is RGB-ordered, matching build_cluster_map's canvas --
    the caller does the one and only RGB->BGR conversion, on the stacked
    map+legend image, right before writing the PNG. Swapping to BGR in here
    too would double-convert these swatches specifically.
    """
    row_height = 32
    swatch_size = 22
    pad = 6
    n_rows = len(rows) + (1 if warning else 0)
    height = row_height * n_rows
    legend = np.full((height, width, 3), (245, 245, 245), dtype=np.uint8)

    y = 0
    for color_rgb, text in rows:
        cv2.rectangle(legend, (pad, y + pad), (pad + swatch_size, y + row_height - pad // 2), color_rgb, thickness=-1)
        cv2.putText(
            legend, text, (pad * 2 + swatch_size, y + row_height - pad),
            cv2.FONT_HERSHEY_SIMPLEX, 0.55, (20, 20, 20), 1, cv2.LINE_AA,
        )
        y += row_height

    if warning:
        cv2.putText(
            legend, warning, (pad, y + row_height - pad),
            cv2.FONT_HERSHEY_SIMPLEX, 0.55, (200, 0, 0), 1, cv2.LINE_AA,
        )

    return legend


def render_legend(legend_rows: list[dict], width: int) -> np.ndarray:
    """legend_rows: [{"cluster_id", "color", "label", "count", "is_unlabeled"}, ...]
    label is expected to already be the display string (real name, or a
    'cluster N' fallback) -- this function just draws it."""
    rows = [(row["color"], f'cluster {row["cluster_id"]}: {row["label"]} ({row["count"]} tiles)') for row in legend_rows]
    rows.append((UNPROCESSED_COLOR, "unprocessed / nodata (dropped tile)"))
    warning = (
        "WARNING: some clusters above are still UNLABELED -- see the label file"
        if any(row["is_unlabeled"] for row in legend_rows)
        else None
    )
    return draw_legend_canvas(rows, width, warning=warning)


def save_image_with_legend(
    canvas_rgb: np.ndarray,
    output_path: str | Path,
    legend_builder=None,
    max_dimension: int = 2500,
) -> None:
    """legend_builder: optional callable(final_width) -> RGB legend array.
    Called *after* the resize below so legend text is drawn crisp at the
    actual output width, instead of being drawn once and rescaled blurry."""
    height, width = canvas_rgb.shape[:2]
    scale = min(1.0, max_dimension / max(height, width))
    if scale < 1.0:
        canvas_rgb = cv2.resize(canvas_rgb, (int(width * scale), int(height * scale)), interpolation=cv2.INTER_NEAREST)
    if legend_builder is not None:
        legend_image = legend_builder(canvas_rgb.shape[1])
        canvas_rgb = np.vstack([canvas_rgb, legend_image])
    bgr = cv2.cvtColor(canvas_rgb, cv2.COLOR_RGB2BGR)
    cv2.imwrite(str(output_path), bgr)


def save_cluster_map(
    canvas_rgb: np.ndarray,
    output_path: str | Path,
    legend_rows: list[dict] | None = None,
    max_dimension: int = 2500,
) -> None:
    builder = (lambda width: render_legend(legend_rows, width)) if legend_rows else None
    save_image_with_legend(canvas_rgb, output_path, legend_builder=builder, max_dimension=max_dimension)
