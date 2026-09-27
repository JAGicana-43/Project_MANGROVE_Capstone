<?php

namespace App\Services;

use App\Models\SamplingPoint;
use App\Models\SoilReading;
use App\Models\User;

/**
 * The mobile sync path's (Api\MobileSyncController) reading-recording
 * logic, kept as its own service rather than inlined in the controller --
 * same duplicate-guard and at-least-one-measurement rule regardless of
 * what future callers this ends up having.
 */
class SoilReadingService
{
    private const MEASUREMENT_FIELDS = [
        'ph_avg', 'salinity_avg', 'moisture_avg', 'nitrogen_avg', 'phosphorus_avg', 'potassium_avg',
    ];

    public function record(
        SamplingPoint $point,
        User $collector,
        array $measurements,
        int $readingCount,
        bool $isSynced,
        ?string $suitabilityRating = null,
        ?string $remarks = null,
        ?string $species = null,
    ): SoilReading {
        abort_if(
            $point->soilReading()->exists(),
            422,
            'A soil reading has already been recorded for this point.'
        );

        $hasAnyMeasurement = collect(self::MEASUREMENT_FIELDS)
            ->contains(fn ($field) => ($measurements[$field] ?? null) !== null);
        abort_unless($hasAnyMeasurement, 422, 'Enter at least one measurement before saving.');

        $reading = SoilReading::create([
            'point_id' => $point->id,
            'species' => $species,
            'collected_by' => $collector->id,
            'ph_avg' => $measurements['ph_avg'] ?? null,
            'salinity_avg' => $measurements['salinity_avg'] ?? null,
            'moisture_avg' => $measurements['moisture_avg'] ?? null,
            'nitrogen_avg' => $measurements['nitrogen_avg'] ?? null,
            'phosphorus_avg' => $measurements['phosphorus_avg'] ?? null,
            'potassium_avg' => $measurements['potassium_avg'] ?? null,
            'reading_count' => $readingCount,
            'suitability_rating' => $suitabilityRating,
            'remarks' => $remarks,
            'is_synced' => $isSynced,
            'synced_at' => $isSynced ? now() : null,
            'collected_at' => now(),
        ]);

        $point->update(['status' => 'collected', 'collected_at' => now()]);

        return $reading;
    }
}
