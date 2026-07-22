<?php

namespace App\Services\Geo;

/**
 * Local-tangent-plane projection, rotation, and polygon primitives used by
 * AoiValidator and MissionGridGenerator.
 *
 * AOIs handled here are small (capped at 25 ha / 500 m longest dimension),
 * so a simple equirectangular projection centered on the polygon's own
 * centroid is accurate enough — no PostGIS/GEOS dependency needed for this
 * math. All rotation/clipping happens in this local (x=east meters,
 * y=north meters) frame; callers project back to lat/lng for output.
 *
 * This mirrors mission_generation_service/geometry.py (the Python module
 * built and tested against the same requirements) so the two stay in
 * agreement on what "self-intersecting," "area," and "longest dimension"
 * mean.
 */
class PolygonGeometry
{
    private const METERS_PER_DEGREE_LAT = 111320.0;
    private const EPSILON = 1e-9;

    /**
     * @param array<int, array{0: float, 1: float}> $polygonLatLng [lat, lng] pairs
     * @return array{points: array<int, array{0: float, 1: float}>, refLat: float, refLng: float}
     */
    public static function toLocalMeters(array $polygonLatLng): array
    {
        $refLat = array_sum(array_column($polygonLatLng, 0)) / count($polygonLatLng);
        $refLng = array_sum(array_column($polygonLatLng, 1)) / count($polygonLatLng);
        $cosRefLat = cos(deg2rad($refLat));

        $points = array_map(function (array $p) use ($refLat, $refLng, $cosRefLat) {
            [$lat, $lng] = $p;
            $x = ($lng - $refLng) * self::METERS_PER_DEGREE_LAT * $cosRefLat;
            $y = ($lat - $refLat) * self::METERS_PER_DEGREE_LAT;
            return [$x, $y];
        }, $polygonLatLng);

        return ['points' => $points, 'refLat' => $refLat, 'refLng' => $refLng];
    }

    /**
     * @param array<int, array{0: float, 1: float}> $pointsXy
     * @return array<int, array{0: float, 1: float}> [lat, lng] pairs
     */
    public static function toLatLng(array $pointsXy, float $refLat, float $refLng): array
    {
        $cosRefLat = cos(deg2rad($refLat));
        $metersPerDegreeLng = $cosRefLat > 1e-9 ? self::METERS_PER_DEGREE_LAT * $cosRefLat : 1e-9;

        return array_map(function (array $p) use ($refLat, $refLng, $metersPerDegreeLng) {
            [$x, $y] = $p;
            $lat = $refLat + $y / self::METERS_PER_DEGREE_LAT;
            $lng = $refLng + $x / $metersPerDegreeLng;
            return [$lat, $lng];
        }, $pointsXy);
    }

    /** Shoelace formula. @param array<int, array{0: float, 1: float}> $pointsXy */
    public static function area(array $pointsXy): float
    {
        $n = count($pointsXy);
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $pointsXy[$i];
            [$x2, $y2] = $pointsXy[($i + 1) % $n];
            $sum += $x1 * $y2 - $x2 * $y1;
        }
        return abs($sum) / 2.0;
    }

    /** @param array<int, array{0: float, 1: float}> $pointsXy */
    public static function centroid(array $pointsXy): array
    {
        $n = count($pointsXy);
        $cx = array_sum(array_column($pointsXy, 0)) / $n;
        $cy = array_sum(array_column($pointsXy, 1)) / $n;
        return [$cx, $cy];
    }

    private static function orientation(array $p, array $q, array $r): int
    {
        $val = ($q[1] - $p[1]) * ($r[0] - $q[0]) - ($q[0] - $p[0]) * ($r[1] - $q[1]);
        if (abs($val) < self::EPSILON) {
            return 0;
        }
        return $val > 0 ? 1 : 2;
    }

    private static function onSegment(array $p, array $q, array $r): bool
    {
        return $q[0] <= max($p[0], $r[0]) + self::EPSILON && $q[0] >= min($p[0], $r[0]) - self::EPSILON
            && $q[1] <= max($p[1], $r[1]) + self::EPSILON && $q[1] >= min($p[1], $r[1]) - self::EPSILON;
    }

    private static function segmentsIntersect(array $p1, array $q1, array $p2, array $q2): bool
    {
        $o1 = self::orientation($p1, $q1, $p2);
        $o2 = self::orientation($p1, $q1, $q2);
        $o3 = self::orientation($p2, $q2, $p1);
        $o4 = self::orientation($p2, $q2, $q1);

        if ($o1 !== $o2 && $o3 !== $o4) {
            return true;
        }
        if ($o1 === 0 && self::onSegment($p1, $p2, $q1)) return true;
        if ($o2 === 0 && self::onSegment($p1, $q2, $q1)) return true;
        if ($o3 === 0 && self::onSegment($p2, $p1, $q2)) return true;
        if ($o4 === 0 && self::onSegment($p2, $q1, $q2)) return true;

        return false;
    }

    /**
     * True if the polygon's boundary crosses itself (non-adjacent edges
     * intersecting). Adjacent edges (sharing a vertex) are never flagged.
     *
     * @param array<int, array{0: float, 1: float}> $pointsXy
     */
    public static function isSelfIntersecting(array $pointsXy): bool
    {
        $n = count($pointsXy);
        if ($n < 4) {
            return false; // a triangle can never self-intersect
        }

        for ($i = 0; $i < $n; $i++) {
            $edgeIStart = $pointsXy[$i];
            $edgeIEnd = $pointsXy[($i + 1) % $n];

            for ($j = $i + 1; $j < $n; $j++) {
                $adjacent = ($j === $i + 1) || ($i === 0 && $j === $n - 1);
                if ($adjacent) {
                    continue;
                }

                $edgeJStart = $pointsXy[$j];
                $edgeJEnd = $pointsXy[($j + 1) % $n];

                if (self::segmentsIntersect($edgeIStart, $edgeIEnd, $edgeJStart, $edgeJEnd)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Andrew's monotone chain. Returns hull vertices in CCW order. */
    public static function convexHull(array $points): array
    {
        $points = array_values($points);
        usort($points, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $n = count($points);
        if ($n < 3) {
            return $points;
        }

        $cross = fn (array $o, array $a, array $b) => ($a[0] - $o[0]) * ($b[1] - $o[1]) - ($a[1] - $o[1]) * ($b[0] - $o[0]);

        $lower = [];
        foreach ($points as $p) {
            while (count($lower) >= 2 && $cross($lower[count($lower) - 2], $lower[count($lower) - 1], $p) <= 0) {
                array_pop($lower);
            }
            $lower[] = $p;
        }

        $upper = [];
        foreach (array_reverse($points) as $p) {
            while (count($upper) >= 2 && $cross($upper[count($upper) - 2], $upper[count($upper) - 1], $p) <= 0) {
                array_pop($upper);
            }
            $upper[] = $p;
        }

        array_pop($lower);
        array_pop($upper);

        return array_merge($lower, $upper);
    }

    /**
     * Minimum-area bounding rectangle via rotating calipers over the convex
     * hull. Returns the angle (deg, CCW from +x/east, mod 180 since a
     * rectangle's long axis has no inherent direction) of the rectangle's
     * longer edge, plus its long/short side lengths in meters.
     *
     * DESIGN DECISION (matches mission_generation_service/geometry.py):
     * rotating the AOI to this axis before generating flight lines is a
     * deliberate, unconfirmed-against-a-rotated-reference-export choice —
     * see MissionGridGenerator for the full reasoning.
     */
    public static function minimumBoundingRectangle(array $pointsXy): array
    {
        $hull = self::convexHull($pointsXy);
        $n = count($hull);

        if ($n < 2) {
            return ['thetaDeg' => 0.0, 'longSide' => 0.0, 'shortSide' => 0.0];
        }

        if ($n === 2) {
            $length = hypot($hull[1][0] - $hull[0][0], $hull[1][1] - $hull[0][1]);
            $angle = fmod(rad2deg(atan2($hull[1][1] - $hull[0][1], $hull[1][0] - $hull[0][0])), 180);
            if ($angle < 0) $angle += 180;
            return ['thetaDeg' => $angle, 'longSide' => $length, 'shortSide' => 0.0];
        }

        $bestArea = INF;
        $best = null;

        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $hull[$i];
            [$x2, $y2] = $hull[($i + 1) % $n];
            $edgeAngle = atan2($y2 - $y1, $x2 - $x1);

            $cosA = cos(-$edgeAngle);
            $sinA = sin(-$edgeAngle);

            $minX = INF; $maxX = -INF; $minY = INF; $maxY = -INF;
            foreach ($hull as [$px, $py]) {
                $rx = $px * $cosA - $py * $sinA;
                $ry = $px * $sinA + $py * $cosA;
                $minX = min($minX, $rx); $maxX = max($maxX, $rx);
                $minY = min($minY, $ry); $maxY = max($maxY, $ry);
            }

            $width = $maxX - $minX;
            $height = $maxY - $minY;
            $area = $width * $height;

            if ($area < $bestArea) {
                $bestArea = $area;
                $long = max($width, $height);
                $short = min($width, $height);
                $angleDeg = rad2deg($edgeAngle) + ($width >= $height ? 0 : 90);
                $angleDeg = fmod($angleDeg, 180);
                if ($angleDeg < 0) $angleDeg += 180;
                $best = ['thetaDeg' => $angleDeg, 'longSide' => $long, 'shortSide' => $short];
            }
        }

        return $best;
    }

    /** @param array<int, array{0: float, 1: float}> $pointsXy */
    public static function rotate(array $pointsXy, float $thetaDeg, array $origin): array
    {
        $theta = deg2rad($thetaDeg);
        $cosT = cos($theta);
        $sinT = sin($theta);
        [$ox, $oy] = $origin;

        return array_map(function (array $p) use ($cosT, $sinT, $ox, $oy) {
            $dx = $p[0] - $ox;
            $dy = $p[1] - $oy;
            return [$dx * $cosT - $dy * $sinT + $ox, $dx * $sinT + $dy * $cosT + $oy];
        }, $pointsXy);
    }

    /** Compass bearing (0-360, clockwise from north/+y) from p1 to p2. */
    public static function bearingDeg(array $p1, array $p2): float
    {
        $dx = $p2[0] - $p1[0];
        $dy = $p2[1] - $p1[1];
        $bearing = rad2deg(atan2($dx, $dy));
        return fmod(fmod($bearing, 360) + 360, 360);
    }

    /**
     * Nudges every vertex outward from the centroid by a tiny fixed distance.
     * Not a true polygon offset — just cheap insurance against the ~1e-9 to
     * 1e-11 m floating-point noise that rotate() introduces, which can
     * otherwise make an edge that should be perfectly straight bow by a
     * similar amount and cause boundary-adjacent scan points to fail
     * pointInPolygon() by a hair (mirrors the equivalent 1e-6 m buffer()
     * workaround in mission_generation_service/waypoint_generator.py).
     */
    public static function inflate(array $pointsXy, float $meters): array
    {
        $centroid = self::centroid($pointsXy);

        return array_map(function (array $p) use ($centroid, $meters) {
            $dx = $p[0] - $centroid[0];
            $dy = $p[1] - $centroid[1];
            $len = hypot($dx, $dy);
            if ($len < 1e-12) {
                return $p;
            }
            $scale = ($len + $meters) / $len;
            return [$centroid[0] + $dx * $scale, $centroid[1] + $dy * $scale];
        }, $pointsXy);
    }

    /** Ray-casting point-in-polygon test (boundary-inclusive within a small tolerance). */
    public static function pointInPolygon(float $x, float $y, array $pointsXy): bool
    {
        $inside = false;
        $n = count($pointsXy);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $pointsXy[$i];
            [$xj, $yj] = $pointsXy[$j];

            $intersects = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Classic scanline-fill edge-crossing test: every polygon edge that
     * straddles y=$y contributes the x where it crosses that height. Sorted
     * ascending, these crossings pair up (0&1, 2&3, ...) into the actual
     * in-polygon segment(s) of the horizontal line y=$y - exactly the
     * entry/exit points a real lawnmower survey pass needs, as opposed to a
     * dense interior raster.
     *
     * Uses the same half-open edge convention as pointInPolygon() (yi > y
     * !== yj > y) so a scanline passing exactly through a shared vertex is
     * counted once, not twice.
     *
     * @param array<int, array{0: float, 1: float}> $pointsXy
     * @return array<int, float> sorted ascending x crossings (even count for a simple polygon)
     */
    public static function scanlineIntersections(array $pointsXy, float $y): array
    {
        $crossings = [];
        $n = count($pointsXy);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $pointsXy[$i];
            [$xj, $yj] = $pointsXy[$j];

            if (($yi > $y) !== ($yj > $y)) {
                $crossings[] = $xi + ($xj - $xi) * ($y - $yi) / ($yj - $yi);
            }
        }

        sort($crossings);

        return $crossings;
    }
}
