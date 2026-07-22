"""
Boustrophedon (lawnmower) waypoint generation over an AOI polygon, rotated to
the AOI's dominant axis rather than a fixed compass direction.

Callers must run `validation.validate_aoi` first — this module assumes it's
being handed an already-accepted AOI and does not re-check area/dimension/
self-intersection caps.
"""
from __future__ import annotations

import math

from shapely.geometry import Point, Polygon
from shapely.prepared import prep

from .drone_profiles import get_drone_profile
from .geometry import (
    bearing_deg,
    dominant_axis,
    make_local_frame,
    polygon_centroid,
    project_polygon,
    rotate_points,
    unproject_points,
)


class WaypointGenerationError(RuntimeError):
    pass


def generate_waypoints(
    polygon_latlng: list[tuple[float, float]],
    drone_key: str,
    # 91.6 matches App\Services\MissionGridGenerator's default, chosen to
    # reproduce a validated real WaypointMap export's ~11.7 m row spacing at
    # 100 m altitude on the Mini 3's ~69.7 deg horizontal FOV. NOTE: unlike
    # the PHP implementation, this module feeds camera_fov_deg straight into
    # the footprint formula below without splitting diagonal->horizontal FOV
    # first (see MissionGridGenerator::splitDiagonalFov) — a pre-existing
    # difference between the two implementations, not something this default
    # change fixes — so this default alone will not reproduce exactly the
    # same spacing here as it does in the live PHP path.
    overlap_percent: float = 91.6,
) -> dict:
    profile = get_drone_profile(drone_key)
    altitude_m = profile["altitude_m"]
    fov_deg = profile["camera_fov_deg"]

    coords = list(polygon_latlng)
    if len(coords) > 3 and coords[0] == coords[-1]:
        coords = coords[:-1]

    frame = make_local_frame(coords)
    points_xy = project_polygon(coords, frame)

    # 1. Footprint width on the ground at this altitude/FOV, then line spacing
    #    shrinks as side-overlap grows (100% overlap -> 0 spacing, floored).
    footprint_width_m = 2 * altitude_m * math.tan(math.radians(fov_deg / 2))
    overlap_fraction = max(0.0, min(99.0, overlap_percent)) / 100.0
    line_spacing_m = footprint_width_m * (1 - overlap_fraction)
    line_spacing_m = max(line_spacing_m, 1.0)
    point_spacing_m = line_spacing_m  # forward overlap reuses the same fraction

    # 2. Rotate the AOI to its dominant axis (see geometry.dominant_axis for
    #    why this is a deliberate, documented design choice rather than always
    #    flying a fixed north-south grid).
    theta_deg, _long_side_m, _short_side_m = dominant_axis(points_xy)
    origin = polygon_centroid(points_xy)
    rotated_points = rotate_points(points_xy, -theta_deg, origin)

    # Rotating introduces ~1e-9 to 1e-11 m floating-point noise per vertex,
    # which is enough to make an edge that should be perfectly straight
    # (e.g. the long side of a rectangle) bow by a similar amount. Scan
    # points that land exactly on that edge can then fall a hair outside it,
    # and shapely's covers() rejects them — silently dropping whole flight
    # lines on rotated AOIs. A microscopic outward buffer makes the
    # containment test tolerant of that noise without meaningfully changing
    # the AOI shape (1e-6 m = 1 micrometer).
    rotated_polygon = prep(Polygon(rotated_points).buffer(1e-6))

    xs = [p[0] for p in rotated_points]
    ys = [p[1] for p in rotated_points]
    min_x, max_x = min(xs), max(xs)
    min_y, max_y = min(ys), max(ys)

    # 3. Sweep lines parallel to the long (x) axis, stacked along the short
    #    (y) axis, clipped to the rotated polygon boundary, zigzagging
    #    direction each line (boustrophedon).
    rotated_waypoints: list[tuple[float, float]] = []
    line_count = 0
    going_forward = True
    y = min_y
    eps = 1e-9
    while y <= max_y + eps:
        line_points = []
        x = min_x
        while x <= max_x + eps:
            if rotated_polygon.covers(Point(x, y)):
                line_points.append((x, y))
            x += point_spacing_m

        if line_points:
            if not going_forward:
                line_points.reverse()
            rotated_waypoints.extend(line_points)
            line_count += 1
            going_forward = not going_forward

        y += line_spacing_m

    if not rotated_waypoints:
        raise WaypointGenerationError(
            "Mission generation produced no waypoints. The AOI may be too "
            "narrow relative to the computed line spacing for altitude/"
            "overlap parameters given."
        )

    # 4. Rotate back to the real-world frame and project to lat/lng.
    final_points_xy = rotate_points(rotated_waypoints, theta_deg, origin)
    final_latlng = unproject_points(final_points_xy, frame)

    # 5. Headings: real bearings for every waypoint (never a lazy 0 default),
    #    even though only the first waypoint has waypointHeadingAngleEnable=1
    #    — matching the real WaypointMap export, which computes genuine
    #    heading values on every waypoint regardless of whether that point's
    #    Enable flag is set.
    waypoints = []
    n = len(final_points_xy)
    for i in range(n):
        if i < n - 1:
            heading = bearing_deg(final_points_xy[i], final_points_xy[i + 1])
        else:
            heading = bearing_deg(final_points_xy[i - 1], final_points_xy[i]) if n > 1 else 0.0

        lat, lng = final_latlng[i]
        waypoints.append(
            {
                "index": i,
                "lat": lat,
                "lng": lng,
                "heading_deg": heading,
                "heading_enable": 1 if i == 0 else 0,
            }
        )

    total_distance_m = 0.0
    for i in range(1, n):
        total_distance_m += math.hypot(
            final_points_xy[i][0] - final_points_xy[i - 1][0],
            final_points_xy[i][1] - final_points_xy[i - 1][1],
        )

    return {
        "waypoints": waypoints,
        "line_spacing_m": round(line_spacing_m, 2),
        "footprint_width_m": round(footprint_width_m, 2),
        "total_distance_m": round(total_distance_m, 2),
        "line_count": line_count,
        "rotation_theta_deg": round(theta_deg, 2),
    }
