<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SamplingPoint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Assigning" a point to a field technician IS "transmitting" it here --
 * there's no separate push step. The mobile app pulls its own assigned
 * list on demand (Api\MobileSyncController::assignedPoints), so the moment
 * a point is assigned it's already visible to that technician next time
 * they sync.
 */
class SamplingPointAssignmentController extends Controller
{
    public function assign(Request $request, Project $project, SamplingPoint $point): JsonResponse
    {
        $user = Auth::user();
        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        $validated = $request->validate([
            'technician_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        // Points have no direct project_id -- same relationship walk used
        // by SoilReadingController and ReportController.
        $point->load('samplingGrid.classificationRun.orthomosaic.areaOfInterest');
        $pointProjectId = $point->samplingGrid?->classificationRun?->orthomosaic?->areaOfInterest?->project_id;
        abort_unless($pointProjectId === $project->id, 404);

        $technician = User::with('role')->findOrFail($validated['technician_id']);
        abort_unless($technician->hasRole('field_technician'), 422, 'Selected user is not a field researcher.');

        $point->update([
            'assigned_to' => $technician->id,
            'status' => 'transmitted',
            'transmitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Point #' . $point->priority_rank . ' assigned to ' . $technician->name . '.',
            'point' => $point->fresh(),
        ]);
    }

    /**
     * Field technicians this researcher created (ResearcherUserController
     * sets approved_by to the creating researcher) -- there's no
     * project-membership row for technicians today, so this is the closest
     * real ownership link the schema already has, rather than inventing a
     * new relationship for this one feature.
     */
    public function technicians(Request $request): JsonResponse
    {
        $user = Auth::user();

        $technicians = User::with('role')
            ->whereHas('role', fn ($q) => $q->where('name', 'field_technician'))
            ->where('approved_by', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['technicians' => $technicians]);
    }
}
