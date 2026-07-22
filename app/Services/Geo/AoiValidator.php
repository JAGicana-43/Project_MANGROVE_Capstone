<?php

namespace App\Services\Geo;

use App\Exceptions\AoiValidationException;

/**
 * AOI validation — must run before any waypoint generation logic, and
 * before an AOI is even saved (see ProjectController::confirmAoi).
 *
 * Mirrors mission_generation_service/validation.py's rules exactly, so a
 * polygon accepted/rejected by the live PHP app matches what the tested
 * Python module would accept/reject.
 */
class AoiValidator
{
    public const MAX_AREA_M2 = 250000.0; // 500 m x 500 m

    // 500 m longest dimension, measured on the polygon's minimum-area
    // bounding rectangle (not the raw axis-aligned lat/lng bbox) — that's
    // the true long-axis length regardless of how the AOI is oriented on
    // the map, and it's the same dimension MissionGridGenerator's rotation
    // logic already computes. Adjust if real-world testing (DJI Pilot 2 /
    // field flights) shows this is too strict/loose for catching long, thin
    // coastal AOIs that stay under the area cap but still blow up
    // flight-line count.
    public const MAX_DIMENSION_M = 500.0;

    /**
     * @param array<int, array{0: float, 1: float}> $polygonLatLng [lat, lng] pairs
     * @return array{area_m2: float, longest_dimension_m: float}
     * @throws AoiValidationException with a specific, frontend-displayable message
     */
    public static function validate(array $polygonLatLng): array
    {
        $coords = array_values($polygonLatLng);

        // Drop an explicit closing vertex if present — the checks below
        // treat the polygon as an implicitly-closed ring.
        if (count($coords) > 3 && $coords[0] == $coords[count($coords) - 1]) {
            array_pop($coords);
        }

        if (count($coords) < 3) {
            throw new AoiValidationException(
                sprintf('AOI must have at least 3 vertices; got %d.', count($coords))
            );
        }

        $local = PolygonGeometry::toLocalMeters($coords);
        $pointsXy = $local['points'];

        if (PolygonGeometry::isSelfIntersecting($pointsXy)) {
            throw new AoiValidationException(
                'AOI polygon is self-intersecting (its boundary crosses itself). '
                . 'Redraw the AOI as a simple (non-crossing) polygon.'
            );
        }

        $areaM2 = PolygonGeometry::area($pointsXy);
        if ($areaM2 > self::MAX_AREA_M2) {
            throw new AoiValidationException(
                sprintf(
                    'AOI area is %s m², which exceeds the %s m² (500 m x 500 m) cap. Draw a smaller area.',
                    number_format($areaM2, 0),
                    number_format(self::MAX_AREA_M2, 0)
                )
            );
        }

        $mbr = PolygonGeometry::minimumBoundingRectangle($pointsXy);
        if ($mbr['longSide'] > self::MAX_DIMENSION_M) {
            throw new AoiValidationException(
                sprintf(
                    'AOI\'s longest dimension is %s m, which exceeds the %s m cap. Long, thin AOIs '
                    . 'generate an excessive number of flight lines even when total area is small '
                    . '— split this AOI into smaller sections.',
                    number_format($mbr['longSide'], 0),
                    number_format(self::MAX_DIMENSION_M, 0)
                )
            );
        }

        return [
            'area_m2' => $areaM2,
            'longest_dimension_m' => $mbr['longSide'],
        ];
    }
}
