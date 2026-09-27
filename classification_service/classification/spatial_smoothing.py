"""Post-hoc majority filter over tile-level cluster assignments.

Does not touch the fitted K-Means model at all -- this runs after
prediction, nudging individual tiles toward whatever cluster is locally
dominant among their spatial neighbors. Real land cover is spatially
autocorrelated; an isolated tile disagreeing with everything around it is
more likely clustering noise (weak feature separation) than a genuine
land-cover change at sub-tile scale.
"""
from __future__ import annotations

from collections import Counter

import numpy as np

from .tiling import Tile


def smooth_labels(tiles: list[Tile], labels: np.ndarray, tile_size: int, window: int = 3) -> np.ndarray:
    """window is in tile-grid units, not pixels -- window=3 means a 3x3
    neighborhood of tiles (~27m x 27m at 9m/tile here). window<=1 disables
    smoothing and returns labels unchanged. Ties keep the tile's own
    original cluster (stability bias) before falling back to the lowest
    cluster id for a deterministic result."""
    if window <= 1:
        return labels.copy()

    radius = window // 2
    position_to_cluster: dict[tuple[int, int], int] = {
        (tile.pixel_row // tile_size, tile.pixel_col // tile_size): int(label)
        for tile, label in zip(tiles, labels)
    }

    smoothed = labels.copy()
    for i, tile in enumerate(tiles):
        tr, tc = tile.pixel_row // tile_size, tile.pixel_col // tile_size
        neighbor_clusters = [
            position_to_cluster[(tr + dr, tc + dc)]
            for dr in range(-radius, radius + 1)
            for dc in range(-radius, radius + 1)
            if (tr + dr, tc + dc) in position_to_cluster
        ]
        counts = Counter(neighbor_clusters)
        max_count = max(counts.values())
        winners = sorted(c for c, n in counts.items() if n == max_count)
        original = int(labels[i])
        smoothed[i] = original if original in winners else winners[0]
    return smoothed
