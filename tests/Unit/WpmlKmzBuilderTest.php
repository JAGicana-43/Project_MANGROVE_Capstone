<?php

namespace Tests\Unit;

use App\Services\MissionGridGenerator;
use App\Services\WpmlKmzBuilder;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Geo\AoiFixtures;

class WpmlKmzBuilderTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputPath = sys_get_temp_dir() . '/wpml_kmz_builder_test_' . uniqid() . '.kmz';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->outputPath)) {
            unlink($this->outputPath);
        }
        parent::tearDown();
    }

    public function test_kmz_structure_matches_reference_conventions(): void
    {
        $droneConfig = [
            'enumValue' => 68,
            'subEnumValue' => 0,
            'cameraFovDeg' => 82.1, // DJI Mini 3 (dji.com/mini-3/specs)
            'globalTransitionalSpeed' => 2.5,
        ];

        $grid = (new MissionGridGenerator())->generate(AoiFixtures::smallAxisAlignedRect(), 100.0, 75.0, $droneConfig['cameraFovDeg']);

        (new WpmlKmzBuilder())->build(
            $grid['waypoints'],
            $droneConfig,
            [
                'altitude' => 100.0,
                'waypointSpeed' => 3.0,
                'gimbalAngle' => -90.0,
                'transitionalSpeed' => $droneConfig['globalTransitionalSpeed'],
            ],
            $this->outputPath
        );

        $n = count($grid['waypoints']);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->outputPath));
        $this->assertSame(2, $zip->numFiles);
        $waylinesXml = $zip->getFromName('wpmz/waylines.wpml');
        $templateXml = $zip->getFromName('wpmz/template.kml');
        $zip->close();

        $this->assertNotFalse($waylinesXml);
        $this->assertNotFalse($templateXml);

        $ns = ['kml' => 'http://www.opengis.net/kml/2.2', 'wpml' => 'http://www.dji.com/wpmz/1.0.2'];

        $waylines = new \SimpleXMLElement($waylinesXml);
        $waylines->registerXPathNamespace('kml', $ns['kml']);
        $waylines->registerXPathNamespace('wpml', $ns['wpml']);

        $distance = (string) $waylines->xpath('//wpml:distance')[0];
        $duration = (string) $waylines->xpath('//wpml:duration')[0];
        $this->assertSame('0', $distance);
        $this->assertSame('0', $duration);

        $placemarks = $waylines->xpath('//kml:Placemark');
        $this->assertCount($n, $placemarks);

        $totalActionGroups = 0;
        foreach ($placemarks as $i => $placemark) {
            $placemark->registerXPathNamespace('kml', $ns['kml']);
            $placemark->registerXPathNamespace('wpml', $ns['wpml']);

            $coordsText = trim((string) $placemark->xpath('.//kml:coordinates')[0]);
            [$lng, $lat] = array_map('floatval', explode(',', $coordsText));
            $this->assertGreaterThan(125.0, $lng);
            $this->assertLessThan(126.0, $lng);
            $this->assertGreaterThan(6.0, $lat);
            $this->assertLessThan(7.0, $lat);

            $headingEnable = (string) $placemark->xpath('.//wpml:waypointHeadingAngleEnable')[0];
            $actionGroups = $placemark->xpath('.//wpml:actionGroup');
            $totalActionGroups += count($actionGroups);

            if ($i === 0) {
                $this->assertSame('1', $headingEnable);
                $this->assertCount(2, $actionGroups);
                $ids = array_map(fn ($g) => (string) $g->xpath('.//wpml:actionGroupId')[0], $actionGroups);
                $this->assertSame(['1', '2'], $ids);
                $funcs = array_map(fn ($g) => (string) $g->xpath('.//wpml:actionActuatorFunc')[0], $actionGroups);
                $this->assertSame(['gimbalRotate', 'gimbalEvenlyRotate'], $funcs);
            } else {
                $this->assertSame('0', $headingEnable);
                $this->assertCount(1, $actionGroups);
                $this->assertSame('2', (string) $actionGroups[0]->xpath('.//wpml:actionGroupId')[0]);
                $this->assertSame('gimbalEvenlyRotate', (string) $actionGroups[0]->xpath('.//wpml:actionActuatorFunc')[0]);
            }
        }

        $this->assertSame($n + 1, $totalActionGroups);

        $template = new \SimpleXMLElement($templateXml);
        $template->registerXPathNamespace('wpml', $ns['wpml']);
        $this->assertSame('2.5', (string) $template->xpath('//wpml:globalTransitionalSpeed')[0]);
        $this->assertSame('goHome', (string) $template->xpath('//wpml:finishAction')[0]);
    }

    /**
     * The AOI polygon (and altitude, which has no sensible generic default)
     * are the only inputs supplied here — overlap %, FOV, gimbal pitch,
     * waypoint speed, drone enum values, and finishAction are all left to
     * their defaults. This should reproduce the validated real-world
     * WaypointMap baseline: ~11.7 m row spacing at 100 m altitude,
     * executeHeight=100, waypointSpeed=3, gimbalPitchRotateAngle=-90,
     * droneEnumValue=68/droneSubEnumValue=0, finishAction=goHome.
     */
    public function test_defaults_alone_reproduce_the_validated_baseline_mission(): void
    {
        $polygon = AoiFixtures::smallAxisAlignedRect();
        $grid = (new MissionGridGenerator())->generate($polygon, 100.0);

        $this->assertEqualsWithDelta(11.7, $grid['lineSpacingMeters'], 0.5);

        (new WpmlKmzBuilder())->build($grid['waypoints'], [], [], $this->outputPath);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->outputPath));
        $waylinesXml = $zip->getFromName('wpmz/waylines.wpml');
        $templateXml = $zip->getFromName('wpmz/template.kml');
        $zip->close();

        $ns = ['kml' => 'http://www.opengis.net/kml/2.2', 'wpml' => 'http://www.dji.com/wpmz/1.0.2'];

        $waylines = new \SimpleXMLElement($waylinesXml);
        $waylines->registerXPathNamespace('kml', $ns['kml']);
        $waylines->registerXPathNamespace('wpml', $ns['wpml']);

        $firstPlacemark = $waylines->xpath('//kml:Placemark')[0];
        $firstPlacemark->registerXPathNamespace('wpml', $ns['wpml']);

        $this->assertSame('100', (string) $firstPlacemark->xpath('.//wpml:executeHeight')[0]);
        $this->assertSame('3', (string) $firstPlacemark->xpath('.//wpml:waypointSpeed')[0]);
        $this->assertSame('-90', (string) $firstPlacemark->xpath('.//wpml:gimbalPitchRotateAngle')[0]);

        $template = new \SimpleXMLElement($templateXml);
        $template->registerXPathNamespace('wpml', $ns['wpml']);
        $this->assertSame('goHome', (string) $template->xpath('//wpml:finishAction')[0]);
        $this->assertSame('68', (string) $template->xpath('//wpml:droneEnumValue')[0]);
        $this->assertSame('0', (string) $template->xpath('//wpml:droneSubEnumValue')[0]);
    }
}
