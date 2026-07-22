"""
Top-level orchestration: validate -> generate waypoints -> build KMZ.

This is the single entry point the rest of the codebase (a Flask route, a
test, a CLI script) should call — it's the thing requirement 5's test harness
exercises before any of this is wired into a real HTTP endpoint.
"""
from __future__ import annotations

from .drone_profiles import get_drone_profile
from .kmz_builder import build_kmz
from .validation import validate_aoi
from .waypoint_generator import generate_waypoints


def generate_mission(
    polygon_latlng: list[tuple[float, float]],
    drone_key: str,
    overlap_percent: float = 91.6,
    # No natural mission-generation default for a file destination — kept
    # positionally 4th (unchanged) for existing callers (app.py,
    # run_test_harness.py) that still pass it positionally; still effectively
    # required, since omitting it fails loudly in build_kmz() rather than
    # silently writing nowhere.
    output_path: str | None = None,
    finish_action: str = "goHome",
) -> dict:
    """
    polygon_latlng: [(lat, lng), ...] — e.g. straight from Leaflet's
    layer.getLatLngs() after converting each LatLng to a plain tuple.

    overlap_percent / finish_action are real parameters with defaults matching
    a validated real-world WaypointMap baseline mission (see
    waypoint_generator.generate_waypoints / kmz_builder._mission_config_block)
    — not hardcoded constants. The AOI polygon is the only thing that has to
    change per call; everything else falls back to these defaults unless
    explicitly overridden.

    Raises AOIValidationError / UnknownDroneModelError / WaypointGenerationError
    on failure, each with a specific, user-displayable message. No silent
    failures — the caller (e.g. a Flask route) should catch these and return
    the message as-is to the frontend.
    """
    aoi_metrics = validate_aoi(polygon_latlng)

    profile = get_drone_profile(drone_key)

    result = generate_waypoints(polygon_latlng, drone_key, overlap_percent)

    mission_params = {
        "altitude_m": profile["altitude_m"],
        "waypoint_speed_mps": profile["waypoint_speed_mps"],
        "gimbal_pitch_deg": profile["gimbal_pitch_deg"],
        "transitional_speed_mps": profile["global_transitional_speed_mps"],
        "finish_action": finish_action,
    }
    build_kmz(result["waypoints"], profile, mission_params, output_path)

    return {
        "output_path": output_path,
        "aoi_area_m2": round(aoi_metrics["area_m2"], 2),
        "aoi_longest_dimension_m": round(aoi_metrics["longest_dimension_m"], 2),
        "waypoint_count": len(result["waypoints"]),
        "line_count": result["line_count"],
        "line_spacing_m": result["line_spacing_m"],
        "footprint_width_m": result["footprint_width_m"],
        "total_distance_m": result["total_distance_m"],
        "rotation_theta_deg": result["rotation_theta_deg"],
        "waypoints": result["waypoints"],
    }
