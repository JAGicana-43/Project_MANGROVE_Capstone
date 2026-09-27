<?php

namespace App\Services;

use App\Models\ClassificationRun;
use App\Models\SamplingPoint;
use App\Models\SoilReading;

/**
 * Computes everything both the live Reports web dashboard
 * (workspace.blade.php) and the generated PDF (ReportController) need,
 * in one place -- fuses classification output (land cover) with soil
 * reading data (suitability ratings, measured parameters, collection
 * timeline) into a single structured summary, so neither renderer
 * duplicates the aggregation logic.
 */
class ReportDataAggregator
{
    private const MEASUREMENT_FIELDS = [
        'ph_avg' => 'pH',
        'salinity_avg' => 'Salinity (ppt)',
        'moisture_avg' => 'Moisture (%)',
        'nitrogen_avg' => 'Nitrogen (ppm)',
        'phosphorus_avg' => 'Phosphorus (ppm)',
        'potassium_avg' => 'Potassium (ppm)',
    ];

    public function summarize(ClassificationRun $run): array
    {
        $grids = $run->samplingGrids;
        $totalCells = $grids->count();
        $coverCounts = $grids->countBy('dominant_cover');

        // Land-cover names, not suitability-tier names -- matches the
        // overlay legend (RunClassificationJob) and the Soil Readings/
        // Sampling Points labeling throughout the app.
        $landCoverBreakdown = collect([
            'bare_soil' => 'Bare Soil',
            'vegetation' => 'Vegetation',
            'open_water' => 'Open Water',
        ])->map(function ($label, $cover) use ($coverCounts, $totalCells) {
            $count = $coverCounts->get($cover, 0);
            return [
                'label' => $label,
                'count' => $count,
                'percent' => $totalCells > 0 ? round($count / $totalCells * 100, 1) : 0,
            ];
        })->values();

        $pointIds = SamplingPoint::whereIn('grid_id', $grids->pluck('id'))->pluck('id');
        $readings = SoilReading::whereIn('point_id', $pointIds)->get();

        $suitabilityBreakdown = collect(['suitable', 'marginal', 'unsuitable'])
            ->map(fn ($rating) => [
                'label' => ucfirst($rating),
                'rating' => $rating,
                'count' => $readings->where('suitability_rating', $rating)->count(),
            ])
            ->values();
        $notRatedCount = $readings->whereNull('suitability_rating')->count();

        $parameterStats = collect(self::MEASUREMENT_FIELDS)->map(function ($label, $field) use ($readings) {
            $values = $readings->pluck($field)->filter(fn ($v) => $v !== null)->values();
            return [
                'field' => $field,
                'label' => $label,
                'count' => $values->count(),
                'min' => $values->isNotEmpty() ? round((float) $values->min(), 2) : null,
                'avg' => $values->isNotEmpty() ? round((float) $values->avg(), 2) : null,
                'max' => $values->isNotEmpty() ? round((float) $values->max(), 2) : null,
                'values' => $values->all(),
            ];
        })->values();

        $collectionTimeline = $readings
            ->filter(fn ($r) => $r->collected_at !== null)
            ->groupBy(fn ($r) => $r->collected_at->format('Y-m-d'))
            ->map(fn ($group, $date) => ['date' => $date, 'count' => $group->count()])
            ->sortKeys()
            ->values();

        // AOI / drone mission come from the same orthomosaic->mission->aoi
        // chain ReportController::generate() already walks to find this
        // run in the first place -- re-derived here from $run itself
        // rather than passed in, so summarize() stays a single-argument
        // call for both the PDF and the live web dashboard.
        $orthomosaic = $run->orthomosaic;
        $aoi = $orthomosaic?->areaOfInterest;
        $mission = $orthomosaic?->mission;
        $missionRequest = $mission?->missionRequest;

        $aoiDetails = $aoi ? [
            'name' => $aoi->name,
            'area_ha' => $aoi->area_sqm !== null ? round($aoi->area_sqm / 10000, 2) : null,
            'center_latitude' => $aoi->center_latitude,
            'center_longitude' => $aoi->center_longitude,
            'status' => $aoi->status,
        ] : null;

        $flightDetails = $mission ? [
            'altitude_m' => $mission->altitude_m,
            'overlap_pct' => $mission->overlap_pct,
            'speed_mps' => $mission->speed_mps,
            'waypoint_count' => $missionRequest?->waypoint_count,
            'line_spacing_meters' => $missionRequest?->line_spacing_meters,
            'total_distance_meters' => $missionRequest?->total_distance_meters,
        ] : null;

        // Ties directly into the species reference-range work: how many
        // readings exist per species and how their suitability ratings
        // actually broke down, not just the raw pH/salinity classification
        // tiers shown per-row in the Soil Readings table.
        $speciesBreakdown = $readings
            ->whereNotNull('species')
            ->groupBy('species')
            ->map(function ($group, $species) {
                return [
                    'species' => $species,
                    'label' => SpeciesReference::label($species),
                    'count' => $group->count(),
                    'suitable' => $group->where('suitability_rating', 'suitable')->count(),
                    'marginal' => $group->where('suitability_rating', 'marginal')->count(),
                    'unsuitable' => $group->where('suitability_rating', 'unsuitable')->count(),
                ];
            })
            ->values();

        return [
            'total_cells' => $totalCells,
            'land_cover_breakdown' => $landCoverBreakdown,
            'total_points' => $pointIds->count(),
            'total_readings' => $readings->count(),
            'suitability_breakdown' => $suitabilityBreakdown,
            'not_rated_count' => $notRatedCount,
            'parameter_stats' => $parameterStats,
            'collection_timeline' => $collectionTimeline,
            'aoi' => $aoiDetails,
            'flight' => $flightDetails,
            'species_breakdown' => $speciesBreakdown,
        ];
    }
}
