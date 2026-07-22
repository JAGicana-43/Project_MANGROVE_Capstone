"""
AOI validation — runs before any waypoint generation logic (requirement 1).

Every rejection raises AOIValidationError with a specific, frontend-displayable
message. Nothing here fails silently.
"""
from __future__ import annotations

from .geometry import (
    dominant_axis,
    is_self_intersecting,
    make_local_frame,
    polygon_area_m2,
    project_polygon,
)

MAX_AREA_M2 = 250_000.0  # 500 m x 500 m

# 500 m longest bounding-box dimension. Measured on the *minimum-area*
# bounding rectangle (not the axis-aligned lat/lng bbox), since that's the
# true long-axis length of the AOI regardless of how it's oriented on the
# map — an axis-aligned bbox can under- or over-state length for a rotated
# shape. This is the same dimension the rotation logic (geometry.dominant_axis)
# already computes, so validation and generation agree on what "long" means.
# Adjust this constant if DJI Pilot 2 import testing shows it's too strict/
# loose for catching long, thin AOIs that stay under the area cap but still
# blow up flight-line count.
MAX_DIMENSION_M = 500.0


class AOIValidationError(ValueError):
    pass


def validate_aoi(polygon_latlng: list[tuple[float, float]]) -> dict:
    """Validates an AOI polygon given as [(lat, lng), ...].

    Returns a dict of computed metrics (area_m2, longest_dimension_m) on
    success. Raises AOIValidationError with a specific message on failure.
    """
    if len(polygon_latlng) < 3:
        raise AOIValidationError(
            f"AOI must have at least 3 vertices; got {len(polygon_latlng)}."
        )

    # Drop an explicit closing vertex if present (first == last) — the ring
    # checks below add the closure themselves and would otherwise see a
    # degenerate zero-length final edge.
    coords = list(polygon_latlng)
    if len(coords) > 3 and coords[0] == coords[-1]:
        coords = coords[:-1]

    frame = make_local_frame(coords)
    points_xy = project_polygon(coords, frame)

    if is_self_intersecting(points_xy):
        raise AOIValidationError(
            "AOI polygon is self-intersecting (its boundary crosses itself). "
            "Redraw the AOI as a simple (non-crossing) polygon."
        )

    area_m2 = polygon_area_m2(points_xy)
    if area_m2 > MAX_AREA_M2:
        raise AOIValidationError(
            f"AOI area is {area_m2:,.0f} m², which exceeds the "
            f"{MAX_AREA_M2:,.0f} m² (500 m x 500 m) cap. Draw a smaller area."
        )

    _theta, long_side_m, _short_side_m = dominant_axis(points_xy)
    if long_side_m > MAX_DIMENSION_M:
        raise AOIValidationError(
            f"AOI's longest dimension is {long_side_m:,.0f} m, which exceeds "
            f"the {MAX_DIMENSION_M:,.0f} m cap. Long, thin AOIs generate an "
            "excessive number of flight lines even when total area is small "
            "— split this AOI into smaller sections."
        )

    return {
        "area_m2": area_m2,
        "longest_dimension_m": long_side_m,
    }
