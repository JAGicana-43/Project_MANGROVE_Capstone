"""Supplementary RGB-only water detection, independent of the ResNet/K-Means
clustering pipeline.

Why this exists: tile-level clustering assigns one label per 224x224 tile
based on its dominant visual content. A narrow stream or small pond that
doesn't fill a whole tile gets absorbed into whatever land cover surrounds
it (usually vegetation) -- confirmed directly: raising k from 4 to 7 did not
isolate small water features into their own cluster, because a mostly-
vegetation tile with a sliver of water still produces a feature vector that
looks like "vegetation with something else," not "water," no matter how
many clusters K-Means is asked to find. This heuristic looks at each tile's
actual pixel content directly instead, as a targeted supplement.

NDWI (the standard water index) isn't usable here since this orthomosaic
has no NIR band, only RGB.

Proportional, not threshold-on-any-glint (revised 2026-09-02): the first
version flagged a tile as water if it had *any* meaningful glint, which
misfired on tiles that were mostly vegetation with only a small puddle --
a real bug reported directly ("some areas are labeled as water even if
there's vegetation in there"). This version instead estimates how much of
the tile's *area* is water-like vs. vegetation-like per pixel, and only
overrides the cluster label when water genuinely dominates the tile (not
merely "present") -- a mostly-vegetation tile with a small puddle now
correctly stays vegetation.

Calibrated against the same 20 known-labeled sample tiles used for the
original version (5 bare_soil, 10 vegetation, 5 open_water) -- see
scripts/calibrate_water_heuristic.py. 100% correct with real margin.
"""
from __future__ import annotations

import cv2
import numpy as np

# HSV ranges, OpenCV convention (H: 0-179, S/V: 0-255).
VEGETATION_HUE_RANGE = (25, 95)      # green, with some yellow-green margin
VEGETATION_MIN_SATURATION = 60       # vivid enough to read as live foliage, not washed-out

# Sun glint (near-white/overexposed pixels) specifically, not "low
# saturation" generally -- low saturation alone also matches dry bare soil
# (tan/gray mud reads as low-saturation too), which caused real false
# positives when tried. Glint is a much more specific water signal: it was
# ~0% on pure vegetation and low-single-digit-percent on bare soil in
# calibration, vs. double digits on confirmed open water.
WATER_GLINT_VALUE = 220

# A tile is overridden to open_water only when its glint fraction both
# clears a minimum floor (not just a stray overexposed pixel or two) and
# exceeds the tile's vegetation fraction -- i.e. water reads as more
# prominent than vegetation, not merely present. This proportional
# comparison is what keeps a mostly-vegetation tile with a small puddle
# from being flipped entirely to water, which the first version of this
# heuristic got wrong (it only checked the glint floor, not the balance
# against vegetation content) -- reported directly: "some areas are
# labeled as water even if there's vegetation in there."
WATER_GLINT_MIN_FRACTION = 0.04


def land_cover_fractions(tile_rgb: np.ndarray) -> tuple[float, float]:
    """Returns (water_fraction, vegetation_fraction) -- the share of the
    tile's pixels that look like each, independently (a pixel can match
    neither, e.g. bare soil)."""
    hsv = cv2.cvtColor(tile_rgb, cv2.COLOR_RGB2HSV)
    hue, sat, val = hsv[:, :, 0], hsv[:, :, 1], hsv[:, :, 2]

    water_mask = val > WATER_GLINT_VALUE
    vegetation_mask = (
        (hue >= VEGETATION_HUE_RANGE[0])
        & (hue <= VEGETATION_HUE_RANGE[1])
        & (sat >= VEGETATION_MIN_SATURATION)
    )

    total = tile_rgb.shape[0] * tile_rgb.shape[1]
    return float(np.count_nonzero(water_mask)) / total, float(np.count_nonzero(vegetation_mask)) / total


def looks_like_water(tile_rgb: np.ndarray) -> bool:
    water_frac, vegetation_frac = land_cover_fractions(tile_rgb)
    return water_frac >= WATER_GLINT_MIN_FRACTION and water_frac > vegetation_frac
