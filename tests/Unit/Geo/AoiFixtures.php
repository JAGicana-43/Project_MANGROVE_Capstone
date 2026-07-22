<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\PolygonGeometry;

/**
 * Synthetic AOI polygons for the PHP test suite, mirroring
 * mission_generation_service/tests/fixtures.py so both implementations are
 * exercised against the same shapes.
 */
class AoiFixtures
{
    public const BASE_LAT = 6.9200;
    public const BASE_LNG = 125.6100;

    private static function toLatLng(array $localPoints, float $refLat, float $refLng): array
    {
        return PolygonGeometry::toLatLng($localPoints, $refLat, $refLng);
    }

    public static function rect(float $refLat, float $refLng, float $widthM, float $heightM, float $rotationDeg = 0.0): array
    {
        $hw = $widthM / 2;
        $hh = $heightM / 2;
        $local = [[-$hw, -$hh], [$hw, -$hh], [$hw, $hh], [-$hw, $hh]];

        if ($rotationDeg !== 0.0) {
            $local = PolygonGeometry::rotate($local, $rotationDeg, [0.0, 0.0]);
        }

        return self::toLatLng($local, $refLat, $refLng);
    }

    public static function lShape(float $refLat, float $refLng, float $sizeM = 90.0): array
    {
        $s = $sizeM;
        $h = $sizeM / 2;
        $local = [[0, 0], [$s, 0], [$s, $h], [$h, $h], [$h, $s], [0, $s]];

        return self::toLatLng($local, $refLat, $refLng);
    }

    public static function bowtie(float $refLat, float $refLng, float $sizeM = 60.0): array
    {
        $s = $sizeM;
        $local = [[0, 0], [$s, $s], [$s, 0], [0, $s]];

        return self::toLatLng($local, $refLat, $refLng);
    }

    public static function smallAxisAlignedRect(): array
    {
        return self::rect(self::BASE_LAT, self::BASE_LNG, 100.0, 70.0);
    }

    public static function smallRotatedRect(): array
    {
        return self::rect(self::BASE_LAT, self::BASE_LNG, 100.0, 70.0, 35.0);
    }

    public static function smallLShape(): array
    {
        return self::lShape(self::BASE_LAT + 0.01, self::BASE_LNG + 0.01, 90.0);
    }

    public static function thinSliverUnderCap(): array
    {
        return self::rect(self::BASE_LAT, self::BASE_LNG - 0.02, 490.0, 15.0);
    }

    public static function thinSliverOverCap(): array
    {
        return self::rect(self::BASE_LAT, self::BASE_LNG - 0.02, 520.0, 15.0);
    }

    public static function selfIntersecting(): array
    {
        return self::bowtie(self::BASE_LAT + 0.02, self::BASE_LNG, 60.0);
    }

    /** Longest side (600 m) also exceeds the dimension cap, but the area check runs first. */
    public static function oversizedRect(): array
    {
        return self::rect(self::BASE_LAT, self::BASE_LNG, 600.0, 450.0);
    }

    /** Three AOIs differing in location, size, AND shape — for the responsiveness self-check. */
    public static function responsivenessSet(): array
    {
        return [
            'small_rect_location_a' => self::smallAxisAlignedRect(),
            'bigger_rect_location_b' => self::rect(self::BASE_LAT + 0.05, self::BASE_LNG + 0.05, 150.0, 120.0),
            'l_shape_location_c' => self::smallLShape(),
        ];
    }
}
