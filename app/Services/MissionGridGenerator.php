<?php

namespace App\Services;

use App\Services\Geo\PolygonGeometry;

/**
 * Boustrophedon (lawnmower) waypoint generation over an AOI polygon,
 * rotated to the AOI's dominant axis rather than a fixed compass direction.
 *
 * Fresh rewrite (not a patch on the old fixed-axis version) — see
 * mission_generation_prompt_1.md. Mirrors
 * mission_generation_service/waypoint_generator.py, the Python module that
 * was built and tested against the same requirements, so both implementations
 * agree on rotation, line spacing, and heading computation.
 *
 * Callers must run App\Services\Geo\AoiValidator::validate() first — this
 * class assumes it's being handed an already-accepted AOI and does not
 * re-check area/dimension/self-intersection caps.
 *
 * Input polygon coordinates are expected as [ [lat, lng], [lat, lng], ... ]
 * (this matches what Leaflet.js gives you from a drawn polygon layer via
 * layer.getLatLngs()).
 *
 * WAYPOINTS-PER-TRANSECT (bug fix, see git history): a real WaypointMap
 * export puts exactly two waypoints on each sweep line — where it enters and
 * exits the AOI — not a dense raster of interior points. The aircraft's
 * camera fires on its own trigger interval mid-flight, independent of
 * waypoint density. Placing extra interior points (the old approach here)
 * made every leg roughly the same length, destroying the long-transect /
 * short-row-step alternation a lawnmower pattern is supposed to have. Each
 * row is now found by intersecting the sweep line with the polygon boundary
 * (a standard scanline-fill test — see PolygonGeometry::scanlineIntersections),
 * which also spans the row's *actual* width instead of a fixed-spacing hop.
 *
 * DIAGONAL VS. HORIZONTAL FOV (bug fix): line spacing is a cross-track
 * (horizontal) measurement, but the camera spec sheet gives the *diagonal*
 * FOV. Feeding the diagonal figure straight into the footprint formula
 * overstates the horizontal footprint on any sensor that isn't square, which
 * silently threw off every downstream spacing number. See
 * splitDiagonalFov() for the derivation (diagonal FOV + sensor aspect ratio
 * -> horizontal/vertical FOV via the shared focal length).
 */
class MissionGridGenerator
{
    // Tiny outward nudge applied to the rotated polygon before boundary
    // tests. Rotating introduces ~1e-9 to 1e-11 m floating-point noise per
    // vertex, enough to bow an edge that should be perfectly straight by a
    // similar amount — a scanline landing exactly on that edge can then miss
    // it by a hair, dropping a row. See PolygonGeometry::inflate() for the
    // equivalent of shapely's buffer() used for the same reason in the
    // Python module.
    private const INFLATE_METERS = 1e-6;

    // A segment shorter than this isn't a real transect - it's scanline
    // noise from a sweep line grazing a single vertex (a local minimum/
    // maximum corner, as opposed to a flat edge): both edges meeting at that
    // corner register as "crossing" at nearly the same x, which should
    // collapse to a zero-width row. In practice the rotate()+inflate()
    // floating-point chain leaves that pair a hair apart (observed up to
    // ~1e-4 m) rather than exactly equal, so a too-small filter here lets a
    // near-duplicate phantom row through - producing a ~0 m leg *and*
    // stealing the row-count/direction-toggle the real first row should
    // get, which is what breaks the long/short alternation right at the
    // start. Half a meter safely clears that noise floor while staying far
    // below any transect meaningful for photogrammetry.
    private const MIN_SEGMENT_METERS = 0.5;

    // If the evenly-stepped rows would leave a gap wider than this fraction
    // of the row spacing uncovered at the AOI's far edge, one extra row is
    // snapped to that edge so the last strip isn't left unphotographed. A
    // generic coverage rule, not a value tied to any one AOI's dimensions.
    private const FINAL_ROW_GAP_TOLERANCE = 0.25;

    /**
     * Splits a lens's diagonal FOV into horizontal/vertical components using
     * the sensor's aspect ratio. A rectilinear lens has one focal length
     * shared by both axes, so tan(fov/2) scales with the sensor's
     * half-width / half-height / half-diagonal in direct proportion:
     *
     *   tan(hFov/2) = (sensorW/2) / f,  tan(vFov/2) = (sensorH/2) / f,
     *   tan(dFov/2) = (sensorDiag/2) / f   where sensorDiag = hypot(W, H)
     *
     * Normalizing W,H to (aspectRatio, 1) so no literal sensor millimeters
     * are needed — only the ratio between them — and solving for f in those
     * units gives the two returned angles in degrees.
     *
     * @return array{horizontalDeg: float, verticalDeg: float}
     */
    public static function splitDiagonalFov(float $diagonalFovDeg, float $sensorAspectRatio): array
    {
        $diagHalfTan = tan(deg2rad($diagonalFovDeg) / 2);
        $diagUnits = hypot($sensorAspectRatio, 1.0);

        $horizontalHalfTan = $sensorAspectRatio * $diagHalfTan / $diagUnits;
        $verticalHalfTan = $diagHalfTan / $diagUnits;

        return [
            'horizontalDeg' => rad2deg(2 * atan($horizontalHalfTan)),
            'verticalDeg' => rad2deg(2 * atan($verticalHalfTan)),
        ];
    }

    /**
     * @param array $polygon Array of [lat, lng] pairs, in order, forming an AOI (already validated).
     * @param float $altitude Flight altitude in meters (relative to takeoff).
     * @param float $overlapPercent Desired side (cross-track) overlap, 0-100. Forward overlap does not
     *                              affect waypoint placement — see the class docblock. Defaults to 91.6,
     *                              which — at 100 m altitude on the DJI Mini 3's 82.1° diagonal / ~69.7°
     *                              horizontal FOV — reproduces the ~11.7 m row spacing of a validated,
     *                              real WaypointMap export. Still fully overridable per call; this is a
     *                              default, not a hardcoded constant.
     * @param float $fovDegrees Camera's diagonal FOV for the selected drone.
     * @param float $sensorAspectRatio Sensor width/height (e.g. 4/3), used to split the diagonal FOV
     *                                 into horizontal/vertical components. Defaults to 4:3.
     *
     * @return array{
     *   waypoints: array<int, array>,
     *   lineSpacingMeters: float,
     *   footprintWidthMeters: float,
     *   totalDistanceMeters: float,
     *   lineCount: int,
     *   rotationThetaDeg: float,
     * }
     */
    public function generate(
        array $polygon,
        float $altitude,
        float $overlapPercent = 91.6,
        float $fovDegrees = 82.1,
        float $sensorAspectRatio = 4 / 3
    ): array {
        $coords = array_values($polygon);
        if (count($coords) > 3 && $coords[0] == $coords[count($coords) - 1]) {
            array_pop($coords);
        }

        if (count($coords) < 3) {
            throw new \InvalidArgumentException('Polygon must have at least 3 points.');
        }

        $local = PolygonGeometry::toLocalMeters($coords);
        $pointsXy = $local['points'];
        $refLat = $local['refLat'];
        $refLng = $local['refLng'];

        // 1. Row spacing (the "short leg" between transects) comes from the
        //    *horizontal* (cross-track) footprint and the side-overlap
        //    percentage only. 100% overlap -> 0 spacing, floored at 1 m.
        $fov = self::splitDiagonalFov($fovDegrees, $sensorAspectRatio);
        $footprintWidthMeters = 2 * $altitude * tan(deg2rad($fov['horizontalDeg']) / 2);
        $overlapFraction = max(0.0, min(99.0, $overlapPercent)) / 100.0;
        $lineSpacingMeters = max($footprintWidthMeters * (1 - $overlapFraction), 1.0);

        // 2. Rotate the AOI to its dominant axis.
        //
        //    DESIGN DECISION (requirement 2 — not confirmed against a rotated
        //    real-world reference export): WaypointMap's actual behavior on a
        //    rotated/diagonal AOI is unknown — neither `mangrove-mission-4.kmz`
        //    nor `output_FINAL.kmz` contains a non-axis-aligned example. We
        //    deliberately rotate flight lines to the AOI's dominant axis (via
        //    minimum-area bounding rectangle) rather than always flying a
        //    fixed north-south grid, because the real target AOIs are
        //    elongated mangrove strips following a coastline, and a
        //    fixed-axis grid would blow up waypoint/turn count on anything
        //    not already axis-aligned. If field/DJI Pilot 2 testing shows
        //    WaypointMap actually uses a fixed axis, this is the one section
        //    to change.
        $mbr = PolygonGeometry::minimumBoundingRectangle($pointsXy);
        $thetaDeg = $mbr['thetaDeg'];
        $origin = PolygonGeometry::centroid($pointsXy);
        $rotatedPoints = PolygonGeometry::rotate($pointsXy, -$thetaDeg, $origin);
        $rotatedPolygonForTest = PolygonGeometry::inflate($rotatedPoints, self::INFLATE_METERS);

        $ys = array_column($rotatedPoints, 1);
        $minY = min($ys);
        $maxY = max($ys);

        // 3. Scan-row Y positions: evenly stepped from the near edge, plus
        //    one final row snapped to the far edge if even stepping would
        //    otherwise leave more than a quarter-row-spacing gap there.
        $rowYs = [];
        for ($y = $minY; $y <= $maxY + 1e-9; $y += $lineSpacingMeters) {
            $rowYs[] = $y;
        }
        if (empty($rowYs)) {
            $rowYs[] = $minY;
        }
        if ($maxY - end($rowYs) > $lineSpacingMeters * self::FINAL_ROW_GAP_TOLERANCE) {
            $rowYs[] = $maxY;
        }

        // 4. For each row, find where it actually crosses the polygon
        //    boundary and emit exactly those entry/exit endpoints — a full-
        //    width transect, not a fixed-spacing raster — zigzagging
        //    direction each row (boustrophedon) so consecutive waypoint
        //    distances alternate between the transect length (long) and the
        //    row spacing (short).
        $rotatedWaypoints = [];
        $lineCount = 0;
        $goingForward = true;

        foreach ($rowYs as $y) {
            $crossings = PolygonGeometry::scanlineIntersections($rotatedPolygonForTest, $y);

            $segments = [];
            for ($i = 0; $i + 1 < count($crossings); $i += 2) {
                if ($crossings[$i + 1] - $crossings[$i] > self::MIN_SEGMENT_METERS) {
                    $segments[] = [$crossings[$i], $crossings[$i + 1]];
                }
            }

            if (empty($segments)) {
                continue;
            }

            $rowPoints = [];
            if ($goingForward) {
                foreach ($segments as [$xStart, $xEnd]) {
                    $rowPoints[] = [$xStart, $y];
                    $rowPoints[] = [$xEnd, $y];
                }
            } else {
                foreach (array_reverse($segments) as [$xStart, $xEnd]) {
                    $rowPoints[] = [$xEnd, $y];
                    $rowPoints[] = [$xStart, $y];
                }
            }

            array_push($rotatedWaypoints, ...$rowPoints);
            $lineCount++;
            $goingForward = !$goingForward;
        }

        if (empty($rotatedWaypoints)) {
            throw new \RuntimeException(
                'Mission generation produced no waypoints. The AOI may be too narrow relative to '
                . 'the computed row spacing for the altitude/overlap parameters given.'
            );
        }

        // 5. Rotate back to the real-world frame and project to lat/lng.
        $finalPointsXy = PolygonGeometry::rotate($rotatedWaypoints, $thetaDeg, $origin);
        $finalLatLng = PolygonGeometry::toLatLng($finalPointsXy, $refLat, $refLng);

        // 6. Headings: real bearings for every waypoint (never a lazy 0
        //    default), even though only the first waypoint has
        //    waypointHeadingAngleEnable=1 — matching the real WaypointMap
        //    export, which computes genuine heading values on every waypoint
        //    regardless of whether that point's Enable flag is set.
        $n = count($finalPointsXy);
        $waypoints = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i < $n - 1) {
                $heading = PolygonGeometry::bearingDeg($finalPointsXy[$i], $finalPointsXy[$i + 1]);
            } else {
                $heading = $n > 1 ? PolygonGeometry::bearingDeg($finalPointsXy[$i - 1], $finalPointsXy[$i]) : 0.0;
            }

            $waypoints[] = [
                'index' => $i,
                'lat' => $finalLatLng[$i][0],
                'lng' => $finalLatLng[$i][1],
                'heading' => $heading,
                'headingEnable' => $i === 0 ? 1 : 0,
            ];
        }

        $totalDistance = 0.0;
        for ($i = 1; $i < $n; $i++) {
            $totalDistance += hypot(
                $finalPointsXy[$i][0] - $finalPointsXy[$i - 1][0],
                $finalPointsXy[$i][1] - $finalPointsXy[$i - 1][1]
            );
        }

        return [
            'waypoints' => $waypoints,
            'lineSpacingMeters' => round($lineSpacingMeters, 2),
            'footprintWidthMeters' => round($footprintWidthMeters, 2),
            'totalDistanceMeters' => round($totalDistance, 2),
            'lineCount' => $lineCount,
            'rotationThetaDeg' => round($thetaDeg, 2),
        ];
    }
}
