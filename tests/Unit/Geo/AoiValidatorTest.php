<?php

namespace Tests\Unit\Geo;

use App\Exceptions\AoiValidationException;
use App\Services\Geo\AoiValidator;
use PHPUnit\Framework\TestCase;

class AoiValidatorTest extends TestCase
{
    public function test_valid_small_rect_passes(): void
    {
        $metrics = AoiValidator::validate(AoiFixtures::smallAxisAlignedRect());

        $this->assertEqualsWithDelta(7000.0, $metrics['area_m2'], 70.0);
        $this->assertEqualsWithDelta(100.0, $metrics['longest_dimension_m'], 2.0);
    }

    public function test_oversized_area_is_rejected(): void
    {
        $this->expectException(AoiValidationException::class);
        $this->expectExceptionMessageMatches('/area/');
        AoiValidator::validate(AoiFixtures::oversizedRect());
    }

    public function test_thin_sliver_under_cap_passes(): void
    {
        $metrics = AoiValidator::validate(AoiFixtures::thinSliverUnderCap());
        $this->assertLessThan(200.0, $metrics['longest_dimension_m']);
    }

    public function test_thin_sliver_over_cap_is_rejected(): void
    {
        $this->expectException(AoiValidationException::class);
        $this->expectExceptionMessageMatches('/longest dimension/');
        AoiValidator::validate(AoiFixtures::thinSliverOverCap());
    }

    public function test_self_intersecting_polygon_is_rejected_not_crashed(): void
    {
        $this->expectException(AoiValidationException::class);
        $this->expectExceptionMessageMatches('/self-intersecting/');
        AoiValidator::validate(AoiFixtures::selfIntersecting());
    }

    public function test_too_few_vertices_is_rejected(): void
    {
        $this->expectException(AoiValidationException::class);
        $this->expectExceptionMessageMatches('/at least 3/');
        AoiValidator::validate([[6.92, 125.61], [6.921, 125.611]]);
    }
}
