"""
Synthetic AOI polygons for the test harness, built in local meters around a
real-ish mangrove-coast reference point (Davao Gulf area, matching the
approximate location seen in the old script's `mangrove-mission-4.kmz`
output) and projected to lat/lng via the same projection the pipeline uses.
"""
from __future__ import annotations

from mission_generation_service.geometry import LocalFrame, rotate_points


def _rect_local(width_m: float, height_m: float) -> list[tuple[float, float]]:
    hw, hh = width_m / 2, height_m / 2
    return [(-hw, -hh), (hw, -hh), (hw, hh), (-hw, hh)]


def rect_latlng(
    base_lat: float,
    base_lng: float,
    width_m: float,
    height_m: float,
    rotation_deg: float = 0.0,
) -> list[tuple[float, float]]:
    frame = LocalFrame(ref_lat=base_lat, ref_lng=base_lng)
    local = _rect_local(width_m, height_m)
    if rotation_deg:
        local = rotate_points(local, rotation_deg, (0.0, 0.0))
    return [frame.to_latlng(x, y) for x, y in local]


def l_shape_latlng(base_lat: float, base_lng: float, size_m: float = 90.0) -> list[tuple[float, float]]:
    """L-shaped hexagon: a `size_m` square with a half-size notch removed
    from one corner."""
    frame = LocalFrame(ref_lat=base_lat, ref_lng=base_lng)
    s = size_m
    h = size_m / 2
    local = [(0, 0), (s, 0), (s, h), (h, h), (h, s), (0, s)]
    return [frame.to_latlng(x, y) for x, y in local]


def bowtie_latlng(base_lat: float, base_lng: float, size_m: float = 60.0) -> list[tuple[float, float]]:
    """Self-intersecting quadrilateral (crossed diagonals) — must be
    rejected by validation before it ever reaches the generator."""
    frame = LocalFrame(ref_lat=base_lat, ref_lng=base_lng)
    s = size_m
    local = [(0, 0), (s, s), (s, 0), (0, s)]
    return [frame.to_latlng(x, y) for x, y in local]


# Reference point: Davao Gulf mangrove coastline area.
BASE_LAT = 6.9200
BASE_LNG = 125.6100

# Test case 1: small axis-aligned rectangle, ~0.7 ha, well under the 4 ha cap.
SMALL_AXIS_ALIGNED_RECT = rect_latlng(BASE_LAT, BASE_LNG, width_m=100.0, height_m=70.0)

# Test case 2: same size, rotated 35 degrees off-axis.
SMALL_ROTATED_RECT = rect_latlng(BASE_LAT, BASE_LNG, width_m=100.0, height_m=70.0, rotation_deg=35.0)

# Test case 3: small irregular L-shaped polygon (6 vertices), ~0.6 ha.
SMALL_L_SHAPE = l_shape_latlng(BASE_LAT + 0.01, BASE_LNG + 0.01, size_m=90.0)

# Test case 4a: thin sliver just *under* the 200 m dimension cap -> should pass.
THIN_SLIVER_UNDER_CAP = rect_latlng(BASE_LAT, BASE_LNG - 0.02, width_m=190.0, height_m=15.0)

# Test case 4b: thin sliver just *over* the 200 m dimension cap -> must be rejected.
THIN_SLIVER_OVER_CAP = rect_latlng(BASE_LAT, BASE_LNG - 0.02, width_m=210.0, height_m=15.0)

# Test case 4c: deliberately self-intersecting polygon -> must be rejected, not crash.
SELF_INTERSECTING = bowtie_latlng(BASE_LAT + 0.02, BASE_LNG)

# Oversized AOI (> 4 ha) for the area-cap rejection test. Longest side (600 m)
# also exceeds the dimension cap, but the area check runs first, so this still
# isolates the area-cap rejection message.
OVERSIZED_RECT = rect_latlng(BASE_LAT, BASE_LNG, width_m=600.0, height_m=450.0)

# For the AOI-responsiveness self-check (requirement 3): three AOIs that
# differ in location, size, AND shape.
RESPONSIVENESS_SET = {
    "small_rect_location_a": SMALL_AXIS_ALIGNED_RECT,
    "bigger_rect_location_b": rect_latlng(BASE_LAT + 0.05, BASE_LNG + 0.05, width_m=150.0, height_m=120.0),
    "l_shape_location_c": SMALL_L_SHAPE,
}
