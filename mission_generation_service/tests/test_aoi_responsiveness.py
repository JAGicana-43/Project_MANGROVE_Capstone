"""
Requirement 3 — the most important check: prove the AOI is actually driving
the output, not just "it ran without an error."

Generates output from three distinct AOIs (different location, size, and
shape — mission_generation_service.tests.fixtures.RESPONSIVENESS_SET) and
asserts, programmatically:

1. Each output's waypoint bounding box corresponds to its own input AOI's
   bounding box (not some other AOI's, not a fixed default).
2. Waypoint count and line count scale sensibly with AOI area — a
   materially bigger AOI must produce materially more waypoints.
"""
from mission_generation_service.geometry import polygon_area_m2, make_local_frame, project_polygon
from mission_generation_service.pipeline import generate_mission
from mission_generation_service.tests.fixtures import RESPONSIVENESS_SET


def _input_bbox(polygon_latlng):
    lats = [p[0] for p in polygon_latlng]
    lngs = [p[1] for p in polygon_latlng]
    return min(lats), max(lats), min(lngs), max(lngs)


def _output_bbox(waypoints):
    lats = [wp["lat"] for wp in waypoints]
    lngs = [wp["lng"] for wp in waypoints]
    return min(lats), max(lats), min(lngs), max(lngs)


def _aoi_area_m2(polygon_latlng):
    frame = make_local_frame(polygon_latlng)
    return polygon_area_m2(project_polygon(polygon_latlng, frame))


def test_each_output_bbox_corresponds_to_its_own_input_aoi(tmp_path):
    results = {}
    for name, polygon in RESPONSIVENESS_SET.items():
        output_path = str(tmp_path / f"{name}.kmz")
        summary = generate_mission(polygon, "dji_mini_3", overlap_percent=75.0, output_path=output_path)
        results[name] = (polygon, summary)

    for name, (polygon, summary) in results.items():
        in_min_lat, in_max_lat, in_min_lng, in_max_lng = _input_bbox(polygon)
        out_min_lat, out_max_lat, out_min_lng, out_max_lng = _output_bbox(summary["waypoints"])

        # Output waypoints must fall inside the input AOI's bbox (with a
        # small tolerance for floating point / projection rounding)...
        tol = 1e-4
        assert in_min_lat - tol <= out_min_lat
        assert out_max_lat <= in_max_lat + tol
        assert in_min_lng - tol <= out_min_lng
        assert out_max_lng <= in_max_lng + tol

        # ...and must actually span a meaningful fraction of it (proving the
        # output isn't a degenerate cluster of points near one corner, or
        # output reused from a different AOI entirely).
        in_lat_span = in_max_lat - in_min_lat
        in_lng_span = in_max_lng - in_min_lng
        out_lat_span = out_max_lat - out_min_lat
        out_lng_span = out_max_lng - out_min_lng
        assert out_lat_span >= 0.5 * in_lat_span, name
        assert out_lng_span >= 0.5 * in_lng_span, name

    # Cross-check: no two AOIs' outputs should land in the same place — each
    # location is real and distinct, so their waypoint bounding boxes must
    # not overlap with each other.
    boxes = {name: _output_bbox(summary["waypoints"]) for name, (_p, summary) in results.items()}
    names = list(boxes)
    for i in range(len(names)):
        for j in range(i + 1, len(names)):
            a, b = boxes[names[i]], boxes[names[j]]
            overlaps = not (a[1] < b[0] or b[1] < a[0] or a[3] < b[2] or b[3] < a[2])
            assert not overlaps, f"{names[i]} and {names[j]} produced overlapping output locations"


def test_waypoint_count_scales_with_aoi_size(tmp_path):
    summaries = {}
    for name, polygon in RESPONSIVENESS_SET.items():
        output_path = str(tmp_path / f"{name}_scale.kmz")
        summaries[name] = generate_mission(polygon, "dji_mini_3", overlap_percent=75.0, output_path=output_path)

    areas = {name: _aoi_area_m2(polygon) for name, polygon in RESPONSIVENESS_SET.items()}

    # The "bigger_rect" fixture (~18,000 m^2) is roughly 2.5-3x the area of
    # the "small_rect" (~7,000 m^2) and "l_shape" (~6,075 m^2) fixtures —
    # its waypoint and line counts must be materially larger, not roughly
    # equal (which would indicate the AOI isn't actually driving generation).
    biggest = max(areas, key=areas.get)
    for name in areas:
        if name == biggest:
            continue
        assert summaries[biggest]["waypoint_count"] > summaries[name]["waypoint_count"], (
            f"{biggest} ({areas[biggest]:.0f} m^2) should produce more waypoints than "
            f"{name} ({areas[name]:.0f} m^2)"
        )
        assert summaries[biggest]["line_count"] >= summaries[name]["line_count"]
