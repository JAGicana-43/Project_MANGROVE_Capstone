<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AoiValidationException;
use App\Http\Controllers\Controller;
use App\Models\DroneMission;
use App\Models\MissionRequest;
use App\Services\Geo\AoiValidator;
use App\Services\MissionGridGenerator;
use App\Services\WpmlKmzBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MissionGenerationController extends Controller
{
    public function generate(
        Request $request,
        MissionGridGenerator $gridGenerator,
        WpmlKmzBuilder $kmzBuilder
    ): JsonResponse {
        $droneKeys = implode(',', array_keys(config('drones', [])));

        $validated = $request->validate([
            'project_id' => ['nullable', 'string'],
            'aoi_id' => ['nullable', 'string'],
            'aoi_polygon' => ['required', 'array', 'min:3'],
            'aoi_polygon.*' => ['required', 'array', 'size:2'],
            'aoi_polygon.*.0' => ['required', 'numeric', 'between:-90,90'],
            'aoi_polygon.*.1' => ['required', 'numeric', 'between:-180,180'],
            'drone_key' => ['required', 'string', 'in:' . $droneKeys],
            'overlap_percent' => ['nullable', 'numeric', 'between:0,99'],
            'finish_action' => ['nullable', 'string', 'in:noAction,autoLand,gotoFirstWaypoint,goHome'],
        ]);

        $droneConfig = config('drones.' . $validated['drone_key']);

        // Altitude, waypoint speed, gimbal angle, and overlap % are all fixed
        // per drone/mission-baseline (requirement 4) and locked in the UI —
        // derived here from the drone's own config / validated defaults
        // rather than trusted from the request, so a request bypassing the
        // UI can't submit different values than what the UI shows as
        // "locked". These are still real function parameters with defaults,
        // not hardcoded constants — an explicit override (e.g. a future
        // per-mission use case) still works, "no override" just now produces
        // the validated real-world WaypointMap baseline instead of an
        // arbitrary previous default.
        $altitude = (float) ($droneConfig['altitudeM'] ?? 100.0);
        $waypointSpeed = (float) ($droneConfig['waypointSpeedMps'] ?? 3.0);
        $gimbalAngle = (float) ($droneConfig['gimbalPitchDeg'] ?? -90.0);
        $overlapPercent = (float) ($validated['overlap_percent'] ?? 91.6);
        $finishAction = $validated['finish_action'] ?? 'goHome';

        // AOI validation runs before any generation logic, and before we even
        // create an audit-trail row for a mission that was never going to
        // succeed.
        try {
            AoiValidator::validate($validated['aoi_polygon']);
        } catch (AoiValidationException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'error' => $exception->getMessage()], 422);
        }

        $missionRequest = MissionRequest::create([
            'user_id' => $request->user()->id,
            'aoi_polygon' => $validated['aoi_polygon'],
            'drone_key' => $validated['drone_key'],
            'altitude' => $altitude,
            'overlap_percent' => $overlapPercent,
            'gimbal_angle' => $gimbalAngle,
            'status' => 'processing',
        ]);

        // Sensor width/height (not just their ratio) are kept as separate
        // config fields for readability and because they're independently
        // meaningful spec values — the ratio is derived here rather than
        // stored as a third, redundant config field that could drift out of
        // sync with the two it's computed from.
        $sensorWidthMm = (float) ($droneConfig['sensorWidthMm'] ?? 9.6);
        $sensorHeightMm = (float) ($droneConfig['sensorHeightMm'] ?? 7.2);

        try {
            $grid = $gridGenerator->generate(
                $validated['aoi_polygon'],
                $altitude,
                $overlapPercent,
                (float) ($droneConfig['cameraFovDeg'] ?? 84.0),
                $sensorWidthMm / $sensorHeightMm,
            );

            $directory = storage_path('app/private/mission-requests');
            File::ensureDirectoryExists($directory);

            $relativePath = 'mission-requests/mission-' . $missionRequest->id . '.kmz';
            $absolutePath = storage_path('app/private/' . $relativePath);

            $kmzBuilder->build(
                $grid['waypoints'],
                $droneConfig,
                [
                    'altitude' => $altitude,
                    'waypointSpeed' => $waypointSpeed,
                    'gimbalAngle' => $gimbalAngle,
                    'transitionalSpeed' => (float) ($droneConfig['globalTransitionalSpeed'] ?? 2.5),
                    'finishAction' => $finishAction,
                ],
                $absolutePath
            );

            // Sidecar file with the exact inputs and derived grid stats for
            // this mission, written next to the .kmz itself — so a bug
            // report attaching a .kmz doesn't require reconstructing the
            // input parameters from the output waypoint geometry.
            File::put(
                storage_path('app/private/mission-requests/mission-' . $missionRequest->id . '.params.json'),
                json_encode([
                    'mission_request_id' => $missionRequest->id,
                    'generated_at' => now()->toIso8601String(),
                    'input' => [
                        'aoi_polygon' => $validated['aoi_polygon'],
                        'drone_key' => $validated['drone_key'],
                        'overlap_percent' => $overlapPercent,
                        'altitude_m' => $altitude,
                        'gimbal_pitch_deg' => $gimbalAngle,
                        'waypoint_speed_mps' => $waypointSpeed,
                        'finish_action' => $finishAction,
                        'drone_config' => $droneConfig,
                        'sensor_aspect_ratio' => $sensorWidthMm / $sensorHeightMm,
                    ],
                    'output' => [
                        'waypoint_count' => count($grid['waypoints']),
                        'line_spacing_meters' => $grid['lineSpacingMeters'],
                        'footprint_width_meters' => $grid['footprintWidthMeters'],
                        'total_distance_meters' => $grid['totalDistanceMeters'],
                        'line_count' => $grid['lineCount'],
                        'rotation_theta_deg' => $grid['rotationThetaDeg'],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $missionRequest->update([
                'waypoint_count' => count($grid['waypoints']),
                'line_spacing_meters' => $grid['lineSpacingMeters'],
                'total_distance_meters' => $grid['totalDistanceMeters'],
                'status' => 'completed',
                'kmz_path' => $relativePath,
                'processed_at' => now(),
            ]);

            if (! empty($validated['aoi_id'])) {
                DroneMission::create([
                    'aoi_id' => $validated['aoi_id'],
                    'created_by' => $request->user()->id,
                    'altitude_m' => $altitude,
                    'overlap_pct' => $overlapPercent,
                    'speed_mps' => $waypointSpeed,
                    'kmz_file_path' => $relativePath,
                    'status' => 'planned',
                    'notes' => 'Generated from mission request #' . $missionRequest->id,
                ]);
            }

            return response()->json([
                'message' => 'Mission file generated successfully.',
                'mission_request_id' => $missionRequest->id,
                'download_url' => route('missions.download', $missionRequest),
                'waypoint_count' => count($grid['waypoints']),
                'total_distance_meters' => $grid['totalDistanceMeters'],
                'line_spacing_meters' => $grid['lineSpacingMeters'],
                'waypoints' => $grid['waypoints'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Mission generation failed.', [
                'mission_request_id' => $missionRequest->id,
                'user_id' => $request->user()->id,
                'exception' => $exception,
            ]);

            $missionRequest->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Mission file could not be generated.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function download(Request $request, MissionRequest $missionRequest): BinaryFileResponse
    {
        abort_unless($missionRequest->user_id === $request->user()->id, 403);
        abort_unless($missionRequest->status === 'completed' && $missionRequest->kmz_path, 404);

        $path = storage_path('app/private/' . $missionRequest->kmz_path);
        abort_unless(File::exists($path), 404);

        return response()->download($path, 'mangrove-mission-' . $missionRequest->id . '.kmz');
    }
}
