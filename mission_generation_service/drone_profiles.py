"""
Per-drone-model constant lookup.

Everything that is a property of the *airframe/camera*, not of a specific
mission, lives here: WPML enum identifiers, camera geometry (needed to turn
altitude+overlap into real line spacing), and the flight constants DJI's own
apps treat as fixed for a given aircraft (cruise/transitional speed, gimbal
pitch, trigger interval).

Overlap percentage is NOT here — it's a per-mission choice (requirement 2
still treats it as a generation input), not an airframe constant.

Adding a new drone model is a data addition to this dict, not a code change
anywhere else in the pipeline — every function that needs drone-specific
values takes `drone_key` and looks it up here.
"""

# global_transitional_speed_mps: the old, untested script used 3 m/s with no
# stated reasoning. `output_FINAL.kmz` (the real WaypointMap export used as
# the authoritative structural reference) uses 2.5 m/s. We deliberately match
# the real export's value here rather than carry forward the old script's
# unexamined constant. This isn't drone-specific — it's a WPML mission-file
# convention.
#
# camera_fov_deg / sensor_*_mm: DJI's published DJI Mini 3 specs
# (dji.com/mini-3/specs) — 82.1 deg FOV, 9.6x7.2mm 1/1.3" sensor.
#
# waypoint_speed_mps: kept at a conservative mapping-survey cruise speed, not
# the aircraft's rated max (16 m/s in S mode) — overlap capture needs a
# controlled, repeatable speed, not maximum speed.
#
# drone_enum_value / drone_sub_enum_value: UNCONFIRMED PLACEHOLDER — flagging
# this rather than silently guessing. DJI's official WPML droneEnumValue
# table (developer.dji.com/doc/cloud-api-tutorial) only documents
# Enterprise-series aircraft: M300/M350 RTK, M30/M30T, M3E/M3T/M3M, M3D/M3TD,
# M400. There is no officially published enum value for the Mini series —
# even DJI's own SDK forum has an open, unanswered question asking for the
# Mini 3 Pro's droneEnumValue. The values below are carried over from the old
# generic placeholder and are NOT confirmed to identify this aircraft
# correctly to any DJI app.
#
# More importantly: DJI Pilot 2 (the "import a KMZ with no drone needed"
# validation step assumed throughout this module's docs) does not run on
# Mini-series hardware at all — it's tied to enterprise RC hardware. The
# Mini 3 uses DJI Fly, which has no built-in waypoint/KMZ import feature.
# The only ways to get a WPML mission onto this aircraft are unofficial:
# manually replacing a placeholder mission file on the RC's filesystem, or a
# third-party bridge tool (e.g. MavenBridge). This module's structural/
# self-check tests still hold, but "confirmed by app import" is not
# available for this aircraft the way it is for Enterprise drones — real
# validation here means the file-swap workaround or an actual flight, not a
# DJI Pilot 2 preview. See README.md's validation-coverage section.
DRONE_PROFILES = {
    "dji_mini_3": {
        "label": "DJI Mini 3",
        "drone_enum_value": 68,
        "drone_sub_enum_value": 0,
        "camera_fov_deg": 82.1,
        "sensor_width_mm": 9.6,
        "sensor_height_mm": 7.2,
        "altitude_m": 100.0,
        "waypoint_speed_mps": 3.0,
        "camera_trigger_interval_s": 2.0,
        "gimbal_pitch_deg": -90.0,
        "global_transitional_speed_mps": 2.5,
    },
}


class UnknownDroneModelError(ValueError):
    pass


def get_drone_profile(drone_key: str) -> dict:
    try:
        return DRONE_PROFILES[drone_key]
    except KeyError:
        known = ", ".join(sorted(DRONE_PROFILES)) or "(none registered)"
        raise UnknownDroneModelError(
            f"Unknown drone model '{drone_key}'. Known models: {known}"
        )
