<?php

namespace App\Services;

use App\Models\AreaOfInterest;
use App\Models\ClassificationRun;
use App\Models\SamplingPoint;

/**
 * Builds GeoJSON FeatureCollections for the GIS export download -- one per
 * geometry type (boundary polygon, grid polygons, sampling points) so each
 * loads as its own correctly-symbolized layer in QGIS/ArcGIS rather than one
 * mixed-geometry layer.
 *
 * Coordinate order is the one real trap here: GeoJSON requires
 * [longitude, latitude], but the source fields are inconsistent --
 * SamplingGrid::cell_bounds is already [minLng, minLat, maxLng, maxLat]
 * (see RunClassificationJob::handle()), while AreaOfInterest::boundary_coordinates
 * and SamplingPoint::latitude/longitude are [lat, lng]. Each builder below
 * converts explicitly rather than assuming a shared convention.
 */
class GisExportService
{
    public function boundaryFeatureCollection(AreaOfInterest $aoi): array
    {
        $coordinates = $aoi->boundary_coordinates ?? [];

        if (count($coordinates) < 3) {
            return $this->featureCollection([]);
        }

        $ring = collect($coordinates)
            ->map(fn ($point) => [(float) $point[1], (float) $point[0]])
            ->values()
            ->all();

        if ($ring[0] !== $ring[count($ring) - 1]) {
            $ring[] = $ring[0];
        }

        return $this->featureCollection([
            [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$ring],
                ],
                'properties' => [
                    'name' => $aoi->name,
                    'status' => $aoi->status,
                    'area_sqm' => $aoi->area_sqm,
                ],
            ],
        ]);
    }

    public function gridFeatureCollection(ClassificationRun $run): array
    {
        $features = $run->samplingGrids->map(function ($cell) {
            [$minLng, $minLat, $maxLng, $maxLat] = $cell->cell_bounds;

            $ring = [
                [$minLng, $minLat],
                [$maxLng, $minLat],
                [$maxLng, $maxLat],
                [$minLng, $maxLat],
                [$minLng, $minLat],
            ];

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$ring],
                ],
                'properties' => [
                    'grid_x' => $cell->grid_x,
                    'grid_y' => $cell->grid_y,
                    'dominant_cover' => $cell->dominant_cover,
                    'suitability_score' => $cell->suitability_score,
                    'manually_overridden' => $cell->manually_overridden,
                ],
            ];
        })->values()->all();

        return $this->featureCollection($features);
    }

    public function pointsFeatureCollection(ClassificationRun $run): array
    {
        $points = SamplingPoint::whereIn('grid_id', $run->samplingGrids->pluck('id'))
            ->with('soilReading')
            ->orderBy('priority_rank')
            ->get();

        $features = $points->map(function (SamplingPoint $point) {
            $reading = $point->soilReading;

            $properties = [
                'priority_rank' => $point->priority_rank,
                'status' => $point->status,
            ];

            if ($reading) {
                $properties = array_merge($properties, [
                    'species' => $reading->species,
                    'ph_avg' => $reading->ph_avg,
                    'salinity_avg' => $reading->salinity_avg,
                    'moisture_avg' => $reading->moisture_avg,
                    'nitrogen_avg' => $reading->nitrogen_avg,
                    'phosphorus_avg' => $reading->phosphorus_avg,
                    'potassium_avg' => $reading->potassium_avg,
                    'suitability_rating' => $reading->suitability_rating,
                    'collected_at' => $reading->collected_at?->toIso8601String(),
                ]);
            }

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $point->longitude, (float) $point->latitude],
                ],
                'properties' => $properties,
            ];
        })->values()->all();

        return $this->featureCollection($features);
    }

    private function featureCollection(array $features): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
