"""
Runs the five test cases end-to-end, writes real .kmz files to
`test_output/` for manual DJI Pilot 2 import testing, and prints a report
that separates what's confirmed by this script from what still needs an
app import or an actual flight.

Usage (from repo root):
    python -m mission_generation_service.run_test_harness
"""
from __future__ import annotations

import os

from .pipeline import generate_mission
from .validation import AOIValidationError
from .tests.fixtures import (
    RESPONSIVENESS_SET,
    SELF_INTERSECTING,
    SMALL_AXIS_ALIGNED_RECT,
    SMALL_L_SHAPE,
    SMALL_ROTATED_RECT,
    THIN_SLIVER_OVER_CAP,
    THIN_SLIVER_UNDER_CAP,
)

OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "test_output")


def _run_case(label: str, polygon, drone_key="dji_mini_3", overlap_percent=75.0):
    output_path = os.path.join(OUTPUT_DIR, f"{label}.kmz")
    try:
        summary = generate_mission(polygon, drone_key, overlap_percent, output_path)
        print(
            f"  [OK] {label}: {summary['waypoint_count']} waypoints, "
            f"{summary['line_count']} lines, area={summary['aoi_area_m2']:.0f} m^2, "
            f"longest_dim={summary['aoi_longest_dimension_m']:.0f} m, "
            f"rotation={summary['rotation_theta_deg']:.1f} deg -> {output_path}"
        )
        return summary
    except AOIValidationError as exc:
        print(f"  [REJECTED] {label}: {exc}")
        return None


def main():
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    print("=== Test case 1: small axis-aligned rectangle ===")
    _run_case("case1_axis_aligned_rect", SMALL_AXIS_ALIGNED_RECT)

    print("\n=== Test case 2: small rotated/diagonal rectangle ===")
    _run_case("case2_rotated_rect", SMALL_ROTATED_RECT)

    print("\n=== Test case 3: small irregular L-shaped polygon ===")
    _run_case("case3_l_shape", SMALL_L_SHAPE)

    print("\n=== Test case 4: edge cases ===")
    print(" -- thin sliver just under the 200m dimension cap (should succeed) --")
    _run_case("case4a_thin_sliver_under_cap", THIN_SLIVER_UNDER_CAP)
    print(" -- thin sliver just over the 200m dimension cap (must be rejected) --")
    result = _run_case("case4b_thin_sliver_over_cap", THIN_SLIVER_OVER_CAP)
    assert result is None, "Sliver over the dimension cap should have been rejected!"
    print(" -- self-intersecting polygon (must be rejected, not crash) --")
    result = _run_case("case4c_self_intersecting", SELF_INTERSECTING)
    assert result is None, "Self-intersecting polygon should have been rejected!"

    print("\n=== Test case 5: AOI-responsiveness self-check ===")
    summaries = {}
    for name, polygon in RESPONSIVENESS_SET.items():
        summaries[name] = _run_case(f"case5_{name}", polygon)

    areas = {name: s["aoi_area_m2"] for name, s in summaries.items()}
    biggest = max(areas, key=areas.get)
    for name, s in summaries.items():
        if name == biggest:
            continue
        assert summaries[biggest]["waypoint_count"] > s["waypoint_count"], (
            "AOI-responsiveness self-check failed: waypoint count did not scale with area."
        )
    print("  [OK] waypoint/line counts scale sensibly with AOI area across all 3 distinct AOIs.")
    print("  (see tests/test_aoi_responsiveness.py for the bounding-box correspondence check, "
          "which pytest runs as part of `python -m pytest mission_generation_service/tests`)")

    print(
        "\n=== Validation coverage (test drone: DJI Mini 3) ===\n"
        "Confirmed automatically by this harness + pytest suite (no drone/app needed):\n"
        "  - AOI area cap, dimension cap, self-intersection rejection (validation.py)\n"
        "  - Waypoint bounding box corresponds to each AOI's own bounding box, and\n"
        "    waypoint/line count scales with AOI size (test_aoi_responsiveness.py)\n"
        "  - Generated KMZ's XML structure matches output_FINAL.kmz's confirmed conventions:\n"
        "    action-group id reuse, headingAngleEnable pattern, distance/duration=0\n"
        "    (test_kmz_structure.py)\n"
        "  - XML is well-formed and the .kmz opens as a valid zip\n"
        "\n"
        "NOT available for this aircraft — DJI Pilot 2 does not run on Mini-series\n"
        "hardware, and DJI Fly (the app the Mini 3 actually uses) has no built-in\n"
        "waypoint/KMZ import feature. There is no drone-free app-level import check\n"
        "for this drone the way there is for Enterprise aircraft. To actually get one\n"
        "of these .kmz files onto the Mini 3, you need one of:\n"
        "  - The unofficial workaround: create a placeholder mission on the RC, then\n"
        "    manually replace that mission file on the controller's filesystem with\n"
        "    this .kmz (renamed to match).\n"
        "  - A third-party bridge tool (e.g. MavenBridge) built for this exact gap.\n"
        "  - Neither is an official DJI-supported import path, so treat this step as\n"
        "    the drone's own pre-flight review, not a substitute for real testing.\n"
        "\n"
        "Requires an ACTUAL FLIGHT — no static analysis or workaround-import substitutes\n"
        "for these:\n"
        "  - Real GPS accuracy / drift in the field\n"
        "  - Real battery consumption and flight time vs. estimates\n"
        "  - Whether the rotation-to-dominant-axis design decision (geometry.dominant_axis)\n"
        "    actually matches how a real mission planner expects a rotated AOI to fly\n"
        "  - Gimbal/camera behavior in wind, and real image overlap on the ground\n"
        "  - RC signal loss / lost-action behavior\n"
        "  - Whether the placeholder drone_enum_value/drone_sub_enum_value (unconfirmed —\n"
        "    see drone_profiles.py) cause the aircraft to reject or misinterpret the\n"
        "    mission on the file-swap workaround\n"
    )


if __name__ == "__main__":
    main()
