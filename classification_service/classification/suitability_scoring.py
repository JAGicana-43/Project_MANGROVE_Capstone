"""Turns grid-cell majority labels into a suitability score for sampling.

Deliberately separate from classification/grid_aggregation.py: the scoring
rule below is a project judgment call (which land-cover types are good
sampling candidates), not something derived from the model, so it needs to
be editable on its own without touching tiling/clustering/aggregation logic.

A score of None means "excluded from scoring, not just low-scored" -- e.g.
open_water is a hard no for sampling, which is a different thing from
dense_vegetation merely scoring low. INSUFFICIENT_DATA cells (from
grid_aggregation) are always excluded too, since there's no reliable label
to score in the first place.
"""
from __future__ import annotations

from collections import Counter
from dataclasses import dataclass
from pathlib import Path

import numpy as np

from .grid_aggregation import INSUFFICIENT_DATA, INSUFFICIENT_DATA_COLOR, GridCell, cell_to_row
from .io_utils import write_rows
from .visualization import UNPROCESSED_COLOR, draw_legend_canvas, save_image_with_legend

# Suitability score per grid-cell majority label, 0.0 (worst) - 1.0 (best).
# None = explicitly excluded/ineligible, not merely scored 0 -- edit this
# dict to change scoring without touching any other module.
DEFAULT_SUITABILITY_SCORES: dict[str, float | None] = {
    "bare_soil": 1.0,
    "sparse_mangrove_fringe": 0.7,
    "dense_vegetation": 0.3,
    "open_water": None,
}

# Distinct from INSUFFICIENT_DATA_COLOR -- this is "we have a confident label
# but the scoring rule excludes it," a different reason than "not enough data."
EXCLUDED_COLOR = (100, 130, 160)
LOW_SCORE_COLOR = (200, 30, 30)   # score -> 0.0
HIGH_SCORE_COLOR = (30, 160, 60)  # score -> 1.0


@dataclass(frozen=True)
class ScoredGridCell:
    cell: GridCell
    suitability_score: float | None  # None if ineligible/unscored
    eligible: bool


def score_grid_cells(
    cells: list[GridCell],
    scoring_config: dict[str, float | None] = DEFAULT_SUITABILITY_SCORES,
) -> list[ScoredGridCell]:
    scored = []
    for cell in cells:
        score = scoring_config.get(cell.majority_label)
        scored.append(ScoredGridCell(cell=cell, suitability_score=score, eligible=score is not None))
    return scored


def save_scored_grid_results(scored_cells: list[ScoredGridCell], output_path: Path) -> None:
    rows = []
    for sc in scored_cells:
        row = cell_to_row(sc.cell)
        row["suitability_score"] = sc.suitability_score
        row["eligible"] = sc.eligible
        rows.append(row)
    write_rows(rows, output_path)


def suitability_color(score: float) -> tuple[int, int, int]:
    score = max(0.0, min(1.0, score))
    return tuple(
        int(LOW_SCORE_COLOR[i] + (HIGH_SCORE_COLOR[i] - LOW_SCORE_COLOR[i]) * score) for i in range(3)
    )


def save_suitability_map(
    scored_cells: list[ScoredGridCell],
    image_height: int,
    image_width: int,
    output_path: Path,
    max_dimension: int = 2500,
) -> None:
    canvas = np.full((image_height, image_width, 3), UNPROCESSED_COLOR, dtype=np.uint8)
    # legend key -> [color, sort_rank, -score (0 for non-eligible rows), cell_count, tile_count]
    legend_entries: dict[str, list] = {}

    def bump(key: str, color: tuple[int, int, int], rank: int, sort_score: float, tile_count: int) -> None:
        entry = legend_entries.setdefault(key, [color, rank, -sort_score, 0, 0])
        entry[3] += 1
        entry[4] += tile_count

    for sc in scored_cells:
        cell = sc.cell
        row0, col0, row1, col1 = cell.pixel_bounds
        if cell.majority_label == INSUFFICIENT_DATA:
            canvas[row0:row1, col0:col1] = INSUFFICIENT_DATA_COLOR
            bump("insufficient data", INSUFFICIENT_DATA_COLOR, 2, 0.0, cell.tile_count)
        elif not sc.eligible:
            canvas[row0:row1, col0:col1] = EXCLUDED_COLOR
            bump(f"{cell.majority_label} (excluded, ineligible)", EXCLUDED_COLOR, 1, 0.0, cell.tile_count)
        else:
            color = suitability_color(sc.suitability_score)
            canvas[row0:row1, col0:col1] = color
            bump(f"{cell.majority_label} (score {sc.suitability_score:.1f})", color, 0, sc.suitability_score, cell.tile_count)

    # Sorted by: eligible-before-excluded-before-insufficient, then best score
    # first within the eligible group -- reads top-to-bottom as best to worst.
    ordered = sorted(legend_entries.items(), key=lambda item: (item[1][1], item[1][2]))
    legend_rows = [(color, f"{key} -- {cells} cells, {tiles} tiles") for key, (color, _rank, _neg_score, cells, tiles) in ordered]
    legend_rows.append((UNPROCESSED_COLOR, "no coverage (outside footprint)"))

    builder = lambda width: draw_legend_canvas(legend_rows, width)  # noqa: E731
    save_image_with_legend(canvas, output_path, legend_builder=builder, max_dimension=max_dimension)


def summarize_scores(scored_cells: list[ScoredGridCell]) -> dict:
    eligible = [sc for sc in scored_cells if sc.eligible]
    excluded = [sc for sc in scored_cells if not sc.eligible]
    tiers = Counter((sc.cell.majority_label, sc.suitability_score) for sc in eligible)
    excluded_reasons = Counter(
        INSUFFICIENT_DATA if sc.cell.majority_label == INSUFFICIENT_DATA else sc.cell.majority_label
        for sc in excluded
    )
    return {
        "total_cells": len(scored_cells),
        "eligible_count": len(eligible),
        "excluded_count": len(excluded),
        "tiers": sorted(
            [{"label": label, "score": score, "count": count} for (label, score), count in tiers.items()],
            key=lambda t: t["score"],
            reverse=True,
        ),
        "excluded_reasons": dict(excluded_reasons),
    }
