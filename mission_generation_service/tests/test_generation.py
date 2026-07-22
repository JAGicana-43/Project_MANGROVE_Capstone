import pytest

from mission_generation_service.waypoint_generator import generate_waypoints
from mission_generation_service.tests.fixtures import (
    SMALL_AXIS_ALIGNED_RECT,
    SMALL_L_SHAPE,
    SMALL_ROTATED_RECT,
)


def _bbox(polygon_latlng):
    lats = [p[0] for p in polygon_latlng]
    lngs = [p[1] for p in polygon_latlng]
    return min(lats), max(lats), min(lngs), max(lngs)


def test_axis_aligned_rect_generates_waypoints_within_its_own_bbox():
    result = generate_waypoints(SMALL_AXIS_ALIGNED_RECT, "dji_mini_3", overlap_percent=75.0)
    assert len(result["waypoints"]) > 0
    assert result["line_count"] > 1

    min_lat, max_lat, min_lng, max_lng = _bbox(SMALL_AXIS_ALIGNED_RECT)
    for wp in result["waypoints"]:
        assert min_lat - 1e-4 <= wp["lat"] <= max_lat + 1e-4
        assert min_lng - 1e-4 <= wp["lng"] <= max_lng + 1e-4


def test_rotated_rect_uses_nonzero_rotation_and_stays_in_bbox():
    result = generate_waypoints(SMALL_ROTATED_RECT, "dji_mini_3", overlap_percent=75.0)
    assert len(result["waypoints"]) > 0

    # The AOI was built with a 35 degree rotation; the dominant-axis
    # detection should recover an angle close to that (mod 180, since a
    # rectangle's long axis has no inherent direction).
    assert result["rotation_theta_deg"] % 180 == pytest.approx(35.0, abs=3.0) or result[
        "rotation_theta_deg"
    ] % 180 == pytest.approx(35.0 + 180, abs=3.0)

    min_lat, max_lat, min_lng, max_lng = _bbox(SMALL_ROTATED_RECT)
    for wp in result["waypoints"]:
        assert min_lat - 1e-4 <= wp["lat"] <= max_lat + 1e-4
        assert min_lng - 1e-4 <= wp["lng"] <= max_lng + 1e-4


def test_l_shape_clips_correctly_and_produces_waypoints():
    result = generate_waypoints(SMALL_L_SHAPE, "dji_mini_3", overlap_percent=75.0)
    assert len(result["waypoints"]) > 0

    min_lat, max_lat, min_lng, max_lng = _bbox(SMALL_L_SHAPE)
    for wp in result["waypoints"]:
        assert min_lat - 1e-4 <= wp["lat"] <= max_lat + 1e-4
        assert min_lng - 1e-4 <= wp["lng"] <= max_lng + 1e-4


def test_only_first_waypoint_has_heading_enabled_but_all_have_real_headings():
    result = generate_waypoints(SMALL_AXIS_ALIGNED_RECT, "dji_mini_3", overlap_percent=75.0)
    waypoints = result["waypoints"]

    assert waypoints[0]["heading_enable"] == 1
    assert all(wp["heading_enable"] == 0 for wp in waypoints[1:])

    # "Real" heading means not every value collapsing to a lazy 0 default —
    # a zigzag path must show at least two distinct heading values.
    headings = {round(wp["heading_deg"], 1) for wp in waypoints}
    assert len(headings) >= 2
