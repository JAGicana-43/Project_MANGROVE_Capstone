import pytest

from mission_generation_service.validation import AOIValidationError, validate_aoi
from mission_generation_service.tests.fixtures import (
    OVERSIZED_RECT,
    SELF_INTERSECTING,
    SMALL_AXIS_ALIGNED_RECT,
    THIN_SLIVER_OVER_CAP,
    THIN_SLIVER_UNDER_CAP,
)


def test_valid_small_rect_passes():
    metrics = validate_aoi(SMALL_AXIS_ALIGNED_RECT)
    assert metrics["area_m2"] == pytest.approx(7000.0, rel=0.01)
    assert metrics["longest_dimension_m"] == pytest.approx(100.0, rel=0.02)


def test_oversized_area_is_rejected():
    with pytest.raises(AOIValidationError, match="area"):
        validate_aoi(OVERSIZED_RECT)


def test_thin_sliver_under_cap_passes():
    metrics = validate_aoi(THIN_SLIVER_UNDER_CAP)
    assert metrics["longest_dimension_m"] < 200.0


def test_thin_sliver_over_cap_is_rejected():
    with pytest.raises(AOIValidationError, match="longest dimension"):
        validate_aoi(THIN_SLIVER_OVER_CAP)


def test_self_intersecting_polygon_is_rejected_not_crashed():
    with pytest.raises(AOIValidationError, match="self-intersecting"):
        validate_aoi(SELF_INTERSECTING)


def test_too_few_vertices_is_rejected():
    with pytest.raises(AOIValidationError, match="at least 3"):
        validate_aoi([(6.92, 125.61), (6.921, 125.611)])
