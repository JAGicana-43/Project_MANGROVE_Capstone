<?php

namespace Tests\Unit;

use App\Services\MissionGridGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Geo\AoiFixtures;

class MissionGridGeneratorTest extends TestCase
{
    private const MINI_3_FOV = 82.1;

    private function bbox(array $polygonLatLng): array
    {
        $lats = array_column($polygonLatLng, 0);
        $lngs = array_column($polygonLatLng, 1);
        return [min($lats), max($lats), min($lngs), max($lngs)];
    }

    public function test_axis_aligned_rect_generates_waypoints_within_its_own_bbox(): void
    {
        $polygon = AoiFixtures::smallAxisAlignedRect();
        $result = (new MissionGridGenerator())->generate($polygon, 100.0, 75.0, self::MINI_3_FOV);

        $this->assertGreaterThan(0, count($result['waypoints']));
        $this->assertGreaterThan(1, $result['lineCount']);

        [$minLat, $maxLat, $minLng, $maxLng] = $this->bbox($polygon);
        foreach ($result['waypoints'] as $wp) {
            $this->assertGreaterThanOrEqual($minLat - 1e-4, $wp['lat']);
            $this->assertLessThanOrEqual($maxLat + 1e-4, $wp['lat']);
            $this->assertGreaterThanOrEqual($minLng - 1e-4, $wp['lng']);
            $this->assertLessThanOrEqual($maxLng + 1e-4, $wp['lng']);
        }
    }

    public function test_rotated_rect_uses_nonzero_rotation_and_stays_in_bbox(): void
    {
        $polygon = AoiFixtures::smallRotatedRect();
        $result = (new MissionGridGenerator())->generate($polygon, 100.0, 75.0, self::MINI_3_FOV);

        $this->assertGreaterThan(0, count($result['waypoints']));

        // Fixture was built with a 35 degree rotation; dominant-axis detection
        // should recover an angle close to that (mod 180 — a rectangle's long
        // axis has no inherent direction).
        $theta = fmod($result['rotationThetaDeg'], 180);
        $this->assertTrue(
            abs($theta - 35.0) < 3.0 || abs($theta - 215.0) < 3.0 || abs($theta + 145.0) < 3.0,
            "Expected rotation near 35 deg (mod 180), got {$result['rotationThetaDeg']}"
        );

        // Same waypoint/line count as the axis-aligned twin (same dimensions,
        // just rotated) — proves rotation isn't silently dropping lines.
        $axisAligned = (new MissionGridGenerator())->generate(AoiFixtures::smallAxisAlignedRect(), 100.0, 75.0, self::MINI_3_FOV);
        $this->assertEquals(count($axisAligned['waypoints']), count($result['waypoints']));
        $this->assertEquals($axisAligned['lineCount'], $result['lineCount']);

        [$minLat, $maxLat, $minLng, $maxLng] = $this->bbox($polygon);
        foreach ($result['waypoints'] as $wp) {
            $this->assertGreaterThanOrEqual($minLat - 1e-4, $wp['lat']);
            $this->assertLessThanOrEqual($maxLat + 1e-4, $wp['lat']);
            $this->assertGreaterThanOrEqual($minLng - 1e-4, $wp['lng']);
            $this->assertLessThanOrEqual($maxLng + 1e-4, $wp['lng']);
        }
    }

    public function test_l_shape_clips_correctly_and_produces_waypoints(): void
    {
        $polygon = AoiFixtures::smallLShape();
        $result = (new MissionGridGenerator())->generate($polygon, 100.0, 75.0, self::MINI_3_FOV);

        $this->assertGreaterThan(0, count($result['waypoints']));

        [$minLat, $maxLat, $minLng, $maxLng] = $this->bbox($polygon);
        foreach ($result['waypoints'] as $wp) {
            $this->assertGreaterThanOrEqual($minLat - 1e-4, $wp['lat']);
            $this->assertLessThanOrEqual($maxLat + 1e-4, $wp['lat']);
            $this->assertGreaterThanOrEqual($minLng - 1e-4, $wp['lng']);
            $this->assertLessThanOrEqual($maxLng + 1e-4, $wp['lng']);
        }
    }

    public function test_only_first_waypoint_has_heading_enabled_but_all_have_real_headings(): void
    {
        $polygon = AoiFixtures::smallAxisAlignedRect();
        $result = (new MissionGridGenerator())->generate($polygon, 100.0, 75.0, self::MINI_3_FOV);
        $waypoints = $result['waypoints'];

        $this->assertSame(1, $waypoints[0]['headingEnable']);
        foreach (array_slice($waypoints, 1) as $wp) {
            $this->assertSame(0, $wp['headingEnable']);
        }

        $headings = array_unique(array_map(fn ($wp) => round($wp['heading'], 1), $waypoints));
        $this->assertGreaterThanOrEqual(2, count($headings));
    }
}
