<?php

namespace Tests\Unit;

use App\Services\Geo\PolygonGeometry;
use App\Services\MissionGridGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Geo\AoiFixtures;

/**
 * Requirement 3 — the most important check: prove the AOI is actually
 * driving the output, not just "it ran without an error." Mirrors
 * mission_generation_service/tests/test_aoi_responsiveness.py.
 */
class AoiResponsivenessTest extends TestCase
{
    private const MINI_3_FOV = 82.1;

    private function inputBbox(array $polygonLatLng): array
    {
        $lats = array_column($polygonLatLng, 0);
        $lngs = array_column($polygonLatLng, 1);
        return [min($lats), max($lats), min($lngs), max($lngs)];
    }

    private function outputBbox(array $waypoints): array
    {
        $lats = array_map(fn ($wp) => $wp['lat'], $waypoints);
        $lngs = array_map(fn ($wp) => $wp['lng'], $waypoints);
        return [min($lats), max($lats), min($lngs), max($lngs)];
    }

    private function aoiAreaM2(array $polygonLatLng): float
    {
        $local = PolygonGeometry::toLocalMeters($polygonLatLng);
        return PolygonGeometry::area($local['points']);
    }

    public function test_each_output_bbox_corresponds_to_its_own_input_aoi(): void
    {
        $generator = new MissionGridGenerator();
        $results = [];

        foreach (AoiFixtures::responsivenessSet() as $name => $polygon) {
            $results[$name] = [$polygon, $generator->generate($polygon, 100.0, 75.0, self::MINI_3_FOV)];
        }

        foreach ($results as $name => [$polygon, $result]) {
            [$inMinLat, $inMaxLat, $inMinLng, $inMaxLng] = $this->inputBbox($polygon);
            [$outMinLat, $outMaxLat, $outMinLng, $outMaxLng] = $this->outputBbox($result['waypoints']);

            $tol = 1e-4;
            $this->assertGreaterThanOrEqual($inMinLat - $tol, $outMinLat, "$name: output min lat outside input bbox");
            $this->assertLessThanOrEqual($inMaxLat + $tol, $outMaxLat, "$name: output max lat outside input bbox");
            $this->assertGreaterThanOrEqual($inMinLng - $tol, $outMinLng, "$name: output min lng outside input bbox");
            $this->assertLessThanOrEqual($inMaxLng + $tol, $outMaxLng, "$name: output max lng outside input bbox");

            $inLatSpan = $inMaxLat - $inMinLat;
            $inLngSpan = $inMaxLng - $inMinLng;
            $outLatSpan = $outMaxLat - $outMinLat;
            $outLngSpan = $outMaxLng - $outMinLng;

            $this->assertGreaterThanOrEqual(0.5 * $inLatSpan, $outLatSpan, "$name: output lat span too small vs input");
            $this->assertGreaterThanOrEqual(0.5 * $inLngSpan, $outLngSpan, "$name: output lng span too small vs input");
        }

        // No two AOIs' outputs should overlap — each location is real and distinct.
        $boxes = [];
        foreach ($results as $name => [$polygon, $result]) {
            $boxes[$name] = $this->outputBbox($result['waypoints']);
        }
        $names = array_keys($boxes);
        for ($i = 0; $i < count($names); $i++) {
            for ($j = $i + 1; $j < count($names); $j++) {
                $a = $boxes[$names[$i]];
                $b = $boxes[$names[$j]];
                $overlaps = !($a[1] < $b[0] || $b[1] < $a[0] || $a[3] < $b[2] || $b[3] < $a[2]);
                $this->assertFalse($overlaps, "{$names[$i]} and {$names[$j]} produced overlapping output locations");
            }
        }
    }

    public function test_waypoint_count_scales_with_aoi_size(): void
    {
        $generator = new MissionGridGenerator();
        $set = AoiFixtures::responsivenessSet();

        $results = [];
        $areas = [];
        foreach ($set as $name => $polygon) {
            $results[$name] = $generator->generate($polygon, 100.0, 75.0, self::MINI_3_FOV);
            $areas[$name] = $this->aoiAreaM2($polygon);
        }

        arsort($areas);
        $biggest = array_key_first($areas);

        foreach (array_keys($results) as $name) {
            if ($name === $biggest) {
                continue;
            }
            $this->assertGreaterThan(
                count($results[$name]['waypoints']),
                count($results[$biggest]['waypoints']),
                "$biggest ({$areas[$biggest]} m^2) should produce more waypoints than $name ({$areas[$name]} m^2)"
            );
            $this->assertGreaterThanOrEqual($results[$name]['lineCount'], $results[$biggest]['lineCount']);
        }
    }
}
