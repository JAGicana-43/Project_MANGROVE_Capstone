<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SamplingPoint;
use App\Services\SoilReadingService;
use App\Services\SpeciesReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deliberately coordinates-and-metadata-only, no imagery -- confirmed with
 * the project owner that the mobile app does not need the orthomosaic or
 * any map overlay, only where to walk to and what to record there.
 */
class MobileSyncController extends Controller
{
    public function assignedPoints(Request $request): JsonResponse
    {
        $points = SamplingPoint::with('samplingGrid.classificationRun.orthomosaic.areaOfInterest.project')
            ->where('assigned_to', $request->user()->id)
            ->where('status', 'transmitted')
            ->orderBy('priority_rank')
            ->get()
            ->map(function (SamplingPoint $point) {
                $aoi = $point->samplingGrid?->classificationRun?->orthomosaic?->areaOfInterest;

                return [
                    'id' => $point->id,
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                    'priority_rank' => $point->priority_rank,
                    'project_name' => $aoi?->project?->name,
                    'aoi_name' => $aoi?->name,
                    'transmitted_at' => $point->transmitted_at?->toIso8601String(),
                ];
            });

        return response()->json(['points' => $points]);
    }

    public function submitReading(Request $request, SamplingPoint $point, SoilReadingService $soilReadingService): JsonResponse
    {
        abort_unless($point->assigned_to === $request->user()->id, 403, 'This point is not assigned to you.');

        $validated = $request->validate([
            'species' => ['nullable', 'string', 'in:' . implode(',', array_keys(SpeciesReference::RANGES))],
            'ph_avg' => ['nullable', 'numeric', 'between:0,14'],
            'salinity_avg' => ['nullable', 'numeric', 'min:0'],
            'moisture_avg' => ['nullable', 'numeric', 'between:0,100'],
            'nitrogen_avg' => ['nullable', 'numeric', 'min:0'],
            'phosphorus_avg' => ['nullable', 'numeric', 'min:0'],
            'potassium_avg' => ['nullable', 'numeric', 'min:0'],
            'reading_count' => ['nullable', 'integer', 'min:1'],
            'suitability_rating' => ['nullable', 'string', 'in:suitable,marginal,unsuitable'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $reading = $soilReadingService->record(
            point: $point,
            collector: $request->user(),
            measurements: $validated,
            readingCount: $validated['reading_count'] ?? 20,
            isSynced: true,
            suitabilityRating: $validated['suitability_rating'] ?? null,
            remarks: $validated['remarks'] ?? null,
            species: $validated['species'] ?? null,
        );

        return response()->json([
            'message' => 'Soil reading synced.',
            'reading' => $reading,
        ]);
    }
}
