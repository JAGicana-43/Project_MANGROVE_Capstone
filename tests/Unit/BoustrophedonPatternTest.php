<?php

namespace Tests\Unit;

use App\Services\Geo\PolygonGeometry;
use App\Services\MissionGridGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Geo\AoiFixtures;

/**
 * Proves the boustrophedon fix: consecutive waypoint distances must
 * alternate between a long "full transect" leg and a short "row step" leg,
 * for AOIs of different sizes - not just the one calibration reference size
 * (a real WaypointMap export over a ~288 x 188 m AOI, ~11.1 m / ~287.7 m
 * alternating legs). That reference is used only to derive the side-overlap
 * percentage below (~92%, see MissionGridGenerator::splitDiagonalFov) -
 * nothing here targets its literal distances or waypoint count.
 *
 * Uses a slightly imperfect quadrilateral (realisticRect()), not a
 * mathematically perfect axis-aligned rectangle, for both test sizes. A
 * perfect rectangle's minimum Y is a full flat edge (two vertices sharing
 * one y); a real hand-drawn AOI never lines up that exactly, so its minimum
 * Y is a single vertex - the shape that actually exercises the scanline's
 * vertex-touching degenerate case a prior bug slipped through (see
 * MissionGridGenerator::MIN_SEGMENT_METERS). Regression coverage for that
 * fix belongs on this shape, not the idealized one.
 */
class BoustrophedonPatternTest extends TestCase
{
    private const MINI_3_FOV = 82.1;
    private const MINI_3_ASPECT = 9.6 / 7.2;
    private const CALIBRATED_SIDE_OVERLAP = 92.0;

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /** @return array<int, float> */
    private function consecutiveDistancesMeters(array $waypoints): array
    {
        $distances = [];
        for ($i = 1; $i < count($waypoints); $i++) {
            $a = $waypoints[$i - 1];
            $b = $waypoints[$i];
            $distances[] = $this->haversineMeters($a['lat'], $a['lng'], $b['lat'], $b['lng']);
        }
        return $distances;
    }

    /** A quadrilateral close to widthM x heightM, but not a perfect rectangle - see class docblock. */
    private function realisticRect(float $refLat, float $refLng, float $widthM, float $heightM): array
    {
        $local = [[0, 0], [$widthM, 3.0], [$widthM, $heightM], [0, $heightM - 2.0]];
        return PolygonGeometry::toLatLng($local, $refLat, $refLng);
    }

    public static function aoiSizeProvider(): array
    {
        return [
            '47m x 46m AOI' => [47.0, 46.0],
            '173m x 97m AOI' => [173.0, 97.0],
        ];
    }

    /** @dataProvider aoiSizeProvider */
    public function test_consecutive_distances_alternate_long_and_short(float $widthM, float $heightM): void
    {
        $polygon = $this->realisticRect(AoiFixtures::BASE_LAT, AoiFixtures::BASE_LNG, $widthM, $heightM);

        $result = (new MissionGridGenerator())->generate(
            $polygon,
            100.0,
            self::CALIBRATED_SIDE_OVERLAP,
            self::MINI_3_FOV,
            self::MINI_3_ASPECT
        );

        $distances = $this->consecutiveDistancesMeters($result['waypoints']);

        fwrite(STDOUT, sprintf(
            "\n[%sm x %sm AOI] %d waypoints, %d rows, row spacing %.2fm\n",
            $widthM,
            $heightM,
            count($result['waypoints']),
            $result['lineCount'],
            $result['lineSpacingMeters']
        ));
        foreach ($distances as $i => $d) {
            fwrite(STDOUT, sprintf("  leg %2d: %7.2f m\n", $i + 1, $d));
        }

        $this->assertGreaterThanOrEqual(3, count($distances), 'Expected at least one full row/step/row cycle.');

        // No duplicate/near-duplicate waypoints anywhere - a prior bug let a
        // phantom near-zero "row" through at the AOI's first vertex-touching
        // scanline row (see MissionGridGenerator::MIN_SEGMENT_METERS).
        foreach ($distances as $i => $d) {
            $this->assertGreaterThan(1.0, $d, "Leg " . ($i + 1) . " is {$d}m - looks like a duplicate waypoint, not a real transect or row-step.");
        }

        // The sequence must start with a full transect, not a stray short
        // hop - a phantom leading row previously stole the first row's
        // direction-toggle, making the *second* real row also come out long
        // instead of alternating from the first leg.
        $this->assertGreaterThan(
            $result['lineSpacingMeters'] * 2,
            $distances[0],
            'First leg should be a long transect, not a short row-step or phantom hop.'
        );

        // Even-indexed legs (0, 2, 4, ...) are the long transects; odd-indexed
        // legs are the short row-steps between them.
        $longLegs = [];
        $shortLegs = [];
        foreach ($distances as $i => $d) {
            if ($i % 2 === 0) {
                $longLegs[] = $d;
            } else {
                $shortLegs[] = $d;
            }
        }

        $this->assertNotEmpty($shortLegs, 'Expected at least one row-step leg.');

        // The two groups must be clearly separated - not the near-uniform
        // sequence the original bug produced.
        $this->assertGreaterThan(
            max($shortLegs) * 2,
            min($longLegs),
            'Long transect legs should be well over double the short row-step legs.'
        );

        // Long legs should span the AOI's long axis - for both AOI sizes,
        // proving the formula scales rather than reproducing a fixed
        // reference number.
        $longAxis = max($widthM, $heightM);
        foreach ($longLegs as $leg) {
            $this->assertEqualsWithDelta($longAxis, $leg, $longAxis * 0.05, "Long leg should span the AOI's long axis ({$longAxis}m).");
        }

        // Short legs should be at most the computed row spacing - exactly
        // that for every regular row-step, except possibly the very last one,
        // which can be shorter when it closes a partial gap against a final
        // row snapped to the AOI's far edge (see FINAL_ROW_GAP_TOLERANCE).
        foreach ($shortLegs as $i => $leg) {
            $this->assertGreaterThan(0.0, $leg);
            $isLastShortLeg = $i === count($shortLegs) - 1;
            if ($isLastShortLeg) {
                $this->assertLessThanOrEqual($result['lineSpacingMeters'] + 1.0, $leg);
            } else {
                $this->assertEqualsWithDelta($result['lineSpacingMeters'], $leg, 1.0, 'Short leg should match the computed row spacing.');
            }
        }
    }

    public function test_calibration_matches_reference_row_spacing_at_100m_altitude(): void
    {
        // Ground truth: a real WaypointMap export at 100 m altitude uses
        // ~11.1 m row spacing. This checks the *formula* reproduces that
        // figure from FOV + aspect ratio + overlap%, not that any AOI in
        // this suite is sized to match the reference AOI.
        $fov = MissionGridGenerator::splitDiagonalFov(self::MINI_3_FOV, self::MINI_3_ASPECT);
        $footprintWidthMeters = 2 * 100.0 * tan(deg2rad($fov['horizontalDeg']) / 2);
        $lineSpacingMeters = $footprintWidthMeters * (1 - self::CALIBRATED_SIDE_OVERLAP / 100.0);

        $this->assertEqualsWithDelta(69.7, $fov['horizontalDeg'], 0.1);
        $this->assertEqualsWithDelta(55.1, $fov['verticalDeg'], 0.1);
        $this->assertEqualsWithDelta(11.1, $lineSpacingMeters, 0.2);
    }
}
