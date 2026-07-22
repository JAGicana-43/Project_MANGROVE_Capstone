"""
Local-tangent-plane projection and rotation helpers.

AOIs here are small (capped at 25 ha / 500 m longest dimension), so a simple
equirectangular projection centered on the polygon's own centroid is accurate
enough — no need for a real map projection library (UTM/pyproj). This keeps
the module dependency-light and matches the meters-per-degree approximation
already used by the existing (pre-rehaul) PHP implementation, so results are
comparable across the two.

All rotation/clipping math happens in this local (x=east meters, y=north
meters) frame, then results are projected back to lat/lng.
"""
from __future__ import annotations

import math
from dataclasses import dataclass

from shapely.geometry import LinearRing, Polygon

METERS_PER_DEGREE_LAT = 111_320.0


@dataclass(frozen=True)
class LocalFrame:
    ref_lat: float
    ref_lng: float

    def to_local(self, lat: float, lng: float) -> tuple[float, float]:
        x = (lng - self.ref_lng) * METERS_PER_DEGREE_LAT * math.cos(math.radians(self.ref_lat))
        y = (lat - self.ref_lat) * METERS_PER_DEGREE_LAT
        return x, y

    def to_latlng(self, x: float, y: float) -> tuple[float, float]:
        lat = self.ref_lat + y / METERS_PER_DEGREE_LAT
        cos_lat = math.cos(math.radians(self.ref_lat))
        meters_per_degree_lng = METERS_PER_DEGREE_LAT * cos_lat if cos_lat > 1e-9 else 1e-9
        lng = self.ref_lng + x / meters_per_degree_lng
        return lat, lng


def make_local_frame(polygon_latlng: list[tuple[float, float]]) -> LocalFrame:
    ref_lat = sum(p[0] for p in polygon_latlng) / len(polygon_latlng)
    ref_lng = sum(p[1] for p in polygon_latlng) / len(polygon_latlng)
    return LocalFrame(ref_lat=ref_lat, ref_lng=ref_lng)


def project_polygon(
    polygon_latlng: list[tuple[float, float]], frame: LocalFrame
) -> list[tuple[float, float]]:
    return [frame.to_local(lat, lng) for lat, lng in polygon_latlng]


def unproject_points(
    points_xy: list[tuple[float, float]], frame: LocalFrame
) -> list[tuple[float, float]]:
    return [frame.to_latlng(x, y) for x, y in points_xy]


def is_self_intersecting(points_xy: list[tuple[float, float]]) -> bool:
    """True if the polygon's boundary crosses itself (excluding the implicit
    closing edge). Uses shapely's ring simplicity check rather than a
    hand-rolled O(n^2) segment sweep."""
    ring = LinearRing(points_xy)
    return not ring.is_simple


def polygon_area_m2(points_xy: list[tuple[float, float]]) -> float:
    return abs(Polygon(points_xy).area)


def dominant_axis(points_xy: list[tuple[float, float]]) -> tuple[float, float, float]:
    """Minimum-area bounding rectangle of the polygon.

    Returns (theta_deg, long_side_m, short_side_m) where theta_deg is the
    angle of the rectangle's longer edge measured counter-clockwise from the
    local +x (east) axis. Rotating the polygon by -theta_deg aligns that long
    edge with the x-axis.

    DESIGN DECISION (requirement 2 — not confirmed against a rotated real-world
    reference export): WaypointMap's actual behavior on a rotated/diagonal AOI
    is unknown — neither `mangrove-mission-4.kmz` nor `output_FINAL.kmz`
    contains a non-axis-aligned example. We deliberately choose to rotate
    flight lines to the AOI's dominant axis (via minimum-area bounding
    rectangle) rather than always flying a fixed north-south grid, because the
    real target AOIs are elongated mangrove strips following a coastline, and
    a fixed-axis grid would blow up waypoint/turn count on anything not
    already axis-aligned. If field testing shows WaypointMap actually uses a
    fixed axis, this is the one function to change.
    """
    polygon = Polygon(points_xy)
    mrr = polygon.minimum_rotated_rectangle
    coords = list(mrr.exterior.coords)[:-1]  # drop repeated closing point

    edges = []
    for i in range(len(coords)):
        (x1, y1), (x2, y2) = coords[i], coords[(i + 1) % len(coords)]
        length = math.hypot(x2 - x1, y2 - y1)
        angle = math.degrees(math.atan2(y2 - y1, x2 - x1))
        edges.append((length, angle))

    # Rectangle has two edge-length values, each appearing twice; take the
    # longer one's angle, normalized to [0, 180) since a line has no direction.
    edges.sort(key=lambda e: e[0], reverse=True)
    long_side, theta = edges[0]
    short_side = edges[-1][0]
    theta = theta % 180.0

    return theta, long_side, short_side


def rotate_points(
    points_xy: list[tuple[float, float]], theta_deg: float, origin: tuple[float, float]
) -> list[tuple[float, float]]:
    theta = math.radians(theta_deg)
    cos_t, sin_t = math.cos(theta), math.sin(theta)
    ox, oy = origin
    rotated = []
    for x, y in points_xy:
        dx, dy = x - ox, y - oy
        rx = dx * cos_t - dy * sin_t + ox
        ry = dx * sin_t + dy * cos_t + oy
        rotated.append((rx, ry))
    return rotated


def polygon_centroid(points_xy: list[tuple[float, float]]) -> tuple[float, float]:
    centroid = Polygon(points_xy).centroid
    return centroid.x, centroid.y


def bearing_deg(p1: tuple[float, float], p2: tuple[float, float]) -> float:
    """Compass bearing (0-360, clockwise from north/+y) from p1 to p2 in the
    local east/north meter frame."""
    dx = p2[0] - p1[0]
    dy = p2[1] - p1[1]
    bearing = math.degrees(math.atan2(dx, dy))
    return bearing % 360.0
