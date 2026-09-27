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
    // Cap generated missions at this fraction of a drone's rated flight
    // time. The rating itself is a best-case, no-wind figure — this margin
    // leaves room for wind, hover time, and the flight-home leg, none of
    // which the rating accounts for.
    private const MAX_BATTERY_UTILIZATION = 0.8;

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
            'altitude_m' => ['nullable', 'numeric', 'between:10,500'],
            'waypoint_speed_mps' => ['nullable', 'numeric', 'between:0.5,15'],
            'gimbal_angle_deg' => ['nullable', 'numeric', 'between:-90,30'],
            'overlap_percent' => ['nullable', 'numeric', 'between:0,99'],
            'finish_action' => ['nullable', 'string', 'in:noAction,autoLand,gotoFirstWaypoint,goHome'],
            'execute_rc_lost_action' => ['nullable', 'string', 'in:goHome,landing,hover'],
            'transitional_speed_mps' => ['nullable', 'numeric', 'between:0.5,15'],
        ]);

        $droneConfig = config('drones.' . $validated['drone_key']);

        // Altitude, waypoint speed, gimbal angle, and overlap % are per-mission
        // photogrammetry choices, adjustable in the UI. Each falls back to the
        // selected drone's config default when the request omits it, so a
        // caller only needs to send what they're actually overriding.
        $altitude = (float) ($validated['altitude_m'] ?? $droneConfig['altitudeM'] ?? 100.0);
        $waypointSpeed = (float) ($validated['waypoint_speed_mps'] ?? $droneConfig['waypointSpeedMps'] ?? 3.0);
        $gimbalAngle = (float) ($validated['gimbal_angle_deg'] ?? $droneConfig['gimbalPitchDeg'] ?? -90.0);
        $overlapPercent = (float) ($validated['overlap_percent'] ?? 91.6);
        $finishAction = $validated['finish_action'] ?? 'goHome';
        $rcLostAction = $validated['execute_rc_lost_action'] ?? 'goHome';
        $transitionalSpeed = (float) ($validated['transitional_speed_mps'] ?? $droneConfig['globalTransitionalSpeed'] ?? 2.5);

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
            'waypoint_speed_mps' => $waypointSpeed,
            'finish_action' => $finishAction,
            'execute_rc_lost_action' => $rcLostAction,
            'transitional_speed_mps' => $transitionalSpeed,
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

            // Reject a mission the aircraft can't physically finish on one
            // battery, before a .kmz is even written. DJI's rated flight
            // time is a best-case, no-wind figure, so we only allow a
            // mission up to MAX_BATTERY_UTILIZATION of it — the margin
            // covers wind, hover time, and the flight-home leg the rated
            // figure doesn't account for. Running out mid-survey means an
            // uncontrolled auto-land wherever the aircraft happens to be,
            // which is exactly the failure mode this exists to prevent.
            $estimatedFlightMinutes = ($grid['totalDistanceMeters'] / max($waypointSpeed, 0.1)) / 60;
            $ratedFlightMinutes = (float) ($droneConfig['batteryFlightTimeMinutes'] ?? 0);
            $safeFlightMinutes = $ratedFlightMinutes * self::MAX_BATTERY_UTILIZATION;

            if ($ratedFlightMinutes > 0 && $estimatedFlightMinutes > $safeFlightMinutes) {
                $message = sprintf(
                    'Estimated flight time (%.1f min) exceeds the %s\'s safe range on one battery '
                    . '(%.1f min, %d%% of its %.0f min rated time). Reduce the AOI size, lower the overlap, '
                    . 'or raise the waypoint speed, then regenerate.',
                    $estimatedFlightMinutes,
                    $droneConfig['label'] ?? $validated['drone_key'],
                    $safeFlightMinutes,
                    self::MAX_BATTERY_UTILIZATION * 100,
                    $ratedFlightMinutes
                );

                $missionRequest->update([
                    'status' => 'failed',
                    'error_message' => $message,
                    'processed_at' => now(),
                ]);

                return response()->json(['message' => $message, 'error' => $message], 422);
            }

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
                    'transitionalSpeed' => $transitionalSpeed,
                    'finishAction' => $finishAction,
                    'executeRCLostAction' => $rcLostAction,
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
                        'execute_rc_lost_action' => $rcLostAction,
                        'transitional_speed_mps' => $transitionalSpeed,
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
                'waypoints' => $grid['waypoints'],
                'status' => 'completed',
                'kmz_path' => $relativePath,
                'processed_at' => now(),
            ]);

            if (! empty($validated['aoi_id'])) {
                DroneMission::create([
                    'aoi_id' => $validated['aoi_id'],
                    'mission_request_id' => $missionRequest->id,
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
