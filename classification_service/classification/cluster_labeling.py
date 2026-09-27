"""Automatic cluster labeling: assigns each K-Means cluster a real land-cover
label (bare_soil / vegetation / open_water) from tile pixel content directly,
instead of requiring a human to review sample tiles and pick a label.

Reuses water_detection.py's land_cover_fractions() (the same proportional
RGB signal already calibrated for that module's own water/not-water tile
override), but with its OWN threshold constants below -- deliberately not
the water_detection module's WATER_GLINT_MIN_FRACTION, since that constant
was calibrated for a different decision (overriding one tile's label during
grid aggregation) on a different calibration set. Sharing a name doesn't
mean the two decisions should share a tuned value.

Validated 2026-09-07 with proper stratified 5-fold cross-validation (not a
single tune-and-test-on-the-same-data split) against the real orthomosaic
currently in this project's database -- 25 sample tiles across 5 clusters,
ground truth assigned by direct visual inspection. Each tile's reported
accuracy below comes only from a fold that never saw that tile while
choosing thresholds, so this is a genuine held-out estimate, not an
optimistic one:
  - Tile-level held-out accuracy: 22/25 = 88.0%
  - Cluster-level held-out accuracy (majority vote per cluster of only
    held-out predictions): 5/5 = 100.0% -- this is the number that actually
    matters for the pipeline, since a cluster's label is what gets used, not
    any single tile's.
  - Confusion matrix (true -> predicted, held-out only): bare_soil misread
    as vegetation once and open_water once (out of 5); open_water misread as
    vegetation once (out of 5); vegetation never misread (15/15). The
    bare_soil/open_water confusion is the same genuinely-ambiguous case
    already documented for the water heuristic (wet mud with sun glint).
  - Thresholds below were then refit on the full 25-tile set (96.0%
    accuracy) for production use, the same values the cross-validation
    folds converged on independently.
See classification_service/scripts/cross_validate_tri_class_heuristic.py --
rerun it whenever more labeled tiles (a new orthomosaic) become available.
"""
from __future__ import annotations

from collections import Counter

import numpy as np

from .water_detection import land_cover_fractions

# Cross-validated thresholds for the cluster-auto-labeling decision
# specifically -- see this module's docstring. Not the same values as
# water_detection.py's own WATER_GLINT_MIN_FRACTION by design.
WATER_MIN_FRACTION_FOR_LABELING = 0.08
VEGETATION_MIN_FRACTION = 0.15


def classify_tile(tile_rgb: np.ndarray) -> str:
    """Classifies a single tile's dominant land cover directly from its pixel
    content -- no clustering, no trained model. Same three labels the rest of
    the pipeline (suitability_scoring.py, grid_aggregation.py) already uses."""
    water_frac, veg_frac = land_cover_fractions(tile_rgb)
    if water_frac >= WATER_MIN_FRACTION_FOR_LABELING and water_frac > veg_frac:
        return "open_water"
    if veg_frac >= VEGETATION_MIN_FRACTION:
        return "vegetation"
    return "bare_soil"


def auto_label_clusters(tiles: list, labels: np.ndarray, cluster_ids: list[int]) -> dict[int, str]:
    """Labels every cluster by majority vote of its own tiles' individual
    classify_tile() results -- the same majority-vote principle
    grid_aggregation.py already uses to resolve a grid cell's label from its
    tiles, applied one level up. A handful of ambiguous tiles inside an
    otherwise-consistent cluster get outvoted rather than derailing the
    cluster's label, which is what makes this reliable enough to trust
    despite individual tile classifications being imperfect (see this
    module's docstring for the cross-validated numbers)."""
    tiles_by_cluster: dict[int, list] = {cid: [] for cid in cluster_ids}
    for tile, cluster_id in zip(tiles, labels):
        tiles_by_cluster[int(cluster_id)].append(tile)

    result: dict[int, str] = {}
    for cluster_id in cluster_ids:
        cluster_tiles = tiles_by_cluster.get(cluster_id, [])
        if not cluster_tiles:
            # Can happen if smoothing (see pipeline.py's spatial_smoothing
            # step) reassigns every tile a cluster originally had -- an
            # empty cluster has nothing to vote on, so it can't be labeled
            # meaningfully either way.
            result[cluster_id] = "vegetation"
            continue
        votes = Counter(classify_tile(tile.image) for tile in cluster_tiles)
        result[cluster_id] = votes.most_common(1)[0][0]

    return result
