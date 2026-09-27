<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SamplingPoint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        $technician = $this->resolveOwnedTechnician($validated['technician_id'], $user);

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
     * Bulk version of assign() -- every one of this project's sampling
     * points that isn't already collected goes to the same technician in
     * one request, instead of clicking through assign() once per point.
     * Reassigns already-transmitted points too (not just untouched ones):
     * deliberately simple semantics ("send everything to this technician"),
     * useful both for the common single-probe case and for handing a whole
     * batch to a different technician later. Collected points are left
     * alone -- they already carry real field data.
     */
    public function assignAll(Request $request, Project $project): JsonResponse
    {
        $user = Auth::user();
        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        $validated = $request->validate([
            'technician_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $technician = $this->resolveOwnedTechnician($validated['technician_id'], $user);

        // Same relationship walk ProjectController::section()'s
        // 'sampling-points' branch uses to list a project's points, just
        // scoped to this one project instead of listed across many.
        $pointIds = SamplingPoint::whereHas(
            'samplingGrid.classificationRun.orthomosaic.areaOfInterest',
            fn ($q) => $q->where('project_id', $project->id)
        )->where('status', '!=', 'collected')->pluck('id');

        SamplingPoint::whereIn('id', $pointIds)->update([
            'assigned_to' => $technician->id,
            'status' => 'transmitted',
            'transmitted_at' => now(),
        ]);

        return response()->json([
            'message' => $pointIds->count() . ' ' . Str::plural('point', $pointIds->count()) . ' assigned to ' . $technician->name . '.',
            'assigned_count' => $pointIds->count(),
            'technician_id' => $technician->id,
        ]);
    }

    /**
     * Same "field researcher, created by this researcher, still active"
     * check technicians() below (and the Sampling Points dropdown that
     * calls it) already applies -- assign()/assignAll() used to only check
     * role, not ownership, so a crafted request with any field_technician
     * id in the system (not just one this researcher created) would have
     * gone through despite never appearing in that researcher's own
     * dropdown. 404, not 403 -- doesn't confirm to the caller whether the
     * id belongs to someone else's technician or doesn't exist at all.
     */
    private function resolveOwnedTechnician(string $technicianId, User $researcher): User
    {
        $technician = User::with('role')
            ->whereHas('role', fn ($q) => $q->where('name', 'field_technician'))
            ->where('approved_by', $researcher->id)
            ->where('is_active', true)
            ->find($technicianId);

        abort_unless($technician, 404, 'Selected user is not one of your field researchers.');

        return $technician;
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
