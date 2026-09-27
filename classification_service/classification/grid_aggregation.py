"""Aggregates tile-level cluster labels into a coarser sampling grid.

Individual 224x224 tiles are finer than what downstream sampling-point
selection needs. This buckets tiles into larger square cells (in source
pixels) and assigns each cell the majority label among the tiles that fall
inside it. Cells with too few contributing tiles are flagged
INSUFFICIENT_DATA rather than guessing off a handful of tiles.
"""
from __future__ import annotations

import json
from collections import Counter
from dataclasses import dataclass
from pathlib import Path

import numpy as np

from .io_utils import write_rows
from .tiling import RasterInfo, Tile
from .visualization import PALETTE, UNPROCESSED_COLOR, draw_legend_canvas, save_image_with_legend
from .water_detection import looks_like_water

INSUFFICIENT_DATA = "INSUFFICIENT_DATA"
INSUFFICIENT_DATA_COLOR = (180, 180, 180)  # light gray, distinct from UNPROCESSED_COLOR's dark gray

# A cell is overridden to open_water if at least this fraction of its
# contributing tiles look water-like by direct pixel content (see
# water_detection.py), regardless of what the cluster-vote majority_label
# says. Requiring a majority (not just one tile) keeps a single stray glint
# tile from flipping a whole multi-tile cell -- moot at the default grid
# size (one tile per cell, so this reduces to "that one tile"), but matters
# if grid_cell_size is ever widened back up.
WATER_OVERRIDE_FRACTION = 0.5


@dataclass(frozen=True)
class GridCell:
    cell_id: int
    row: int
    col: int
    pixel_bounds: tuple[int, int, int, int]  # row0, col0, row1, col1
    geo_bounds: tuple[float, float, float, float]  # min_x, min_y, max_x, max_y
    centroid: tuple[float, float]  # x, y
    tile_count: int
    label_counts: dict[str, int]
    majority_label: str  # INSUFFICIENT_DATA if tile_count < min_valid_tiles


def aggregate_to_grid(
    tiles: list[Tile],
    labels: np.ndarray,
    cluster_labels: dict[int, str],
    info: RasterInfo,
    cell_size: int,
    min_valid_tiles: int = 1,
) -> list[GridCell]:
    if cell_size < 224:
        raise ValueError("cell_size must be >= the tile size (224px) for a cell to actually cover multiple tiles.")

    buckets: dict[tuple[int, int], list[int]] = {}
    water_flags: dict[tuple[int, int], list[bool]] = {}
    for tile, label in zip(tiles, labels):
        bucket = (tile.pixel_row // cell_size, tile.pixel_col // cell_size)
        buckets.setdefault(bucket, []).append(int(label))
        water_flags.setdefault(bucket, []).append(looks_like_water(tile.image))

    cells = []
    for cell_id, ((row_bucket, col_bucket), cluster_ids) in enumerate(sorted(buckets.items())):
        label_names = [cluster_labels.get(cid, str(cid)) for cid in cluster_ids]
        counts = Counter(label_names)
        tile_count = len(cluster_ids)
        majority_label = counts.most_common(1)[0][0] if tile_count >= min_valid_tiles else INSUFFICIENT_DATA

        # Supplementary override: the cluster vote above only sees which
        # cluster each tile's overall appearance matched, which can miss a
        # tile whose water content doesn't dominate its 224x224 crop (see
        # water_detection.py's docstring -- confirmed directly that raising
        # k does not fix this). Checking actual pixel content catches those.
        if majority_label != INSUFFICIENT_DATA:
            flags = water_flags[(row_bucket, col_bucket)]
            if flags and (sum(flags) / len(flags)) >= WATER_OVERRIDE_FRACTION:
                majority_label = "open_water"

        row0, col0 = row_bucket * cell_size, col_bucket * cell_size
        row1 = min(row0 + cell_size, info.height)
        col1 = min(col0 + cell_size, info.width)
        x0, y0 = info.transform * (col0, row0)
        x1, y1 = info.transform * (col1, row1)
        geo_bounds = (min(x0, x1), min(y0, y1), max(x0, x1), max(y0, y1))
        centroid = ((geo_bounds[0] + geo_bounds[2]) / 2, (geo_bounds[1] + geo_bounds[3]) / 2)

        cells.append(
            GridCell(
                cell_id=cell_id,
                row=row_bucket,
                col=col_bucket,
                pixel_bounds=(row0, col0, row1, col1),
                geo_bounds=geo_bounds,
                centroid=centroid,
                tile_count=tile_count,
                label_counts=dict(counts),
                majority_label=majority_label,
            )
        )
    return cells


def cell_to_row(cell: GridCell) -> dict:
    """Base row shape shared by grid_results.csv and (extended) suitability_results.csv."""
    min_x, min_y, max_x, max_y = cell.geo_bounds
    return {
        "cell_id": cell.cell_id,
        "row": cell.row,
        "col": cell.col,
        "geo_min_x": min_x,
        "geo_min_y": min_y,
        "geo_max_x": max_x,
        "geo_max_y": max_y,
        "centroid_x": cell.centroid[0],
        "centroid_y": cell.centroid[1],
        "tile_count": cell.tile_count,
        "majority_label": cell.majority_label,
        "label_counts": json.dumps(cell.label_counts),
    }


def save_grid_results(cells: list[GridCell], output_path: Path) -> None:
    write_rows([cell_to_row(cell) for cell in cells], output_path)


def _label_colors(cells: list[GridCell]) -> dict[str, tuple[int, int, int]]:
    labels = sorted({cell.majority_label for cell in cells if cell.majority_label != INSUFFICIENT_DATA})
    return {label: PALETTE[i % len(PALETTE)] for i, label in enumerate(labels)}


def save_grid_map(
    cells: list[GridCell],
    image_height: int,
    image_width: int,
    output_path: Path,
    max_dimension: int = 2500,
) -> None:
    label_colors = _label_colors(cells)
    canvas = np.full((image_height, image_width, 3), UNPROCESSED_COLOR, dtype=np.uint8)
    counts_by_label: dict[str, int] = Counter()
    tiles_by_label: dict[str, int] = Counter()
    insufficient_cells = 0

    for cell in cells:
        row0, col0, row1, col1 = cell.pixel_bounds
        if cell.majority_label == INSUFFICIENT_DATA:
            canvas[row0:row1, col0:col1] = INSUFFICIENT_DATA_COLOR
            insufficient_cells += 1
        else:
            canvas[row0:row1, col0:col1] = label_colors[cell.majority_label]
            counts_by_label[cell.majority_label] += 1
            tiles_by_label[cell.majority_label] += cell.tile_count

    legend_rows = [
        (label_colors[label], f"{label} ({counts_by_label[label]} cells, {tiles_by_label[label]} tiles)")
        for label in sorted(label_colors)
    ]
    legend_rows.append((INSUFFICIENT_DATA_COLOR, f"insufficient data ({insufficient_cells} cells)"))
    legend_rows.append((UNPROCESSED_COLOR, "no coverage (outside footprint)"))

    builder = lambda width: draw_legend_canvas(legend_rows, width)  # noqa: E731
    save_image_with_legend(canvas, output_path, legend_builder=builder, max_dimension=max_dimension)
