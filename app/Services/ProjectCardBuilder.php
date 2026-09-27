<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared by DashboardController::lobby() (the Atlas rail's recent-projects
 * strip) and ProjectController::index() (the full "All Projects" list) so
 * both read the exact same phase/AOI-shape/member-chip logic instead of two
 * copies drifting apart.
 */
class ProjectCardBuilder
{
    public const PHASE_LABELS = ['AOI', 'Mission', 'Orthomosaic', 'Classify', 'Sample'];

    public function loadForUser(User $user): Collection
    {
        return Project::query()
            ->withCount(['members as member_count'])
            ->with([
                'members.user',
                'areasOfInterest.droneMissions',
                'areasOfInterest.orthomosaics.classificationRuns.samplingGrids.samplingPoints.soilReading',
                'areasOfInterest.surveyComparisons.orthomosaicA',
                'areasOfInterest.surveyComparisons.orthomosaicB',
                'reports',
            ])
            ->where('created_by', $user->id)
            ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();
    }

    public function build(Collection $projects, User $user): Collection
    {
        $phaseLabels = self::PHASE_LABELS;

        return $projects->map(function (Project $project) use ($user, $phaseLabels) {
            $aois = $project->areasOfInterest;
            $orthomosaics = $aois->flatMap->orthomosaics;
            $missions = $aois->flatMap->droneMissions;
            $runs = $orthomosaics->flatMap->classificationRuns;
            $completedRuns = $runs->where('status', 'completed');
            $grids = $completedRuns->flatMap->samplingGrids;
            $points = $grids->flatMap->samplingPoints;

            $phaseIndex = $this->computePhaseIndex($missions, $orthomosaics, $completedRuns, $points);

            $memberChips = $project->members
                ->map(fn ($member) => $member->user)
                ->filter()
                ->take(3)
                ->map(function ($member) {
                    $name = trim($member->name ?? '');
                    return [
                        'name' => $name !== '' ? $name : 'Unknown user',
                        'avatar_url' => $member->avatar_url,
                        'initial' => $name !== '' ? strtoupper(substr($name, 0, 1)) : 'U',
                    ];
                })->values();

            // Real AOI outline instead of a generic decorative graphic --
            // the primary/first AOI's boundary, normalized into a 0-100
            // viewBox. Latitude is flipped (SVG y grows downward, latitude
            // grows northward) so the shape isn't rendered upside down.
            $primaryAoi = $aois->firstWhere('status', 'confirmed') ?? $aois->first();
            $aoiShapePoints = null;
            if ($primaryAoi && is_array($primaryAoi->boundary_coordinates) && count($primaryAoi->boundary_coordinates) >= 3) {
                $lats = array_column($primaryAoi->boundary_coordinates, 0);
                $lngs = array_column($primaryAoi->boundary_coordinates, 1);
                $latMin = min($lats); $latMax = max($lats);
                $lngMin = min($lngs); $lngMax = max($lngs);
                $latSpan = max($latMax - $latMin, 0.0000001);
                $lngSpan = max($lngMax - $lngMin, 0.0000001);
                $pad = 12;

                $aoiShapePoints = collect($primaryAoi->boundary_coordinates)->map(function ($point) use ($latMin, $latSpan, $lngMin, $lngSpan, $pad) {
                    $x = $pad + (($point[1] - $lngMin) / $lngSpan) * (100 - 2 * $pad);
                    $y = $pad + (1 - ($point[0] - $latMin) / $latSpan) * (100 - 2 * $pad);
                    return round($x, 1) . ',' . round($y, 1);
                })->implode(' ');
            }

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status ?? 'active',
                'created_at' => $project->created_at ? $project->created_at->format('M d, Y') : 'Recently created',
                'updated_at' => $project->updated_at ? $project->updated_at->diffForHumans() : 'Recently updated',
                'updated_at_raw' => $project->updated_at,
                'aoi_count' => $aois->count(),
                'member_count' => (int) ($project->member_count ?? 0),
                'members' => $memberChips,
                'is_creator' => $project->created_by === $user->id,
                'phase_index' => $phaseIndex,
                'phase_labels' => $phaseLabels,
                'aoi_shape_points' => $aoiShapePoints,
                'orthomosaic_count' => $orthomosaics->count(),
                'point_count' => $points->count(),
                'points_unassigned' => $points->where('status', 'pending')->count(),
                'points_assigned' => $points->where('status', 'transmitted')->count(),
                'points_collected' => $points->where('status', 'collected')->count(),
                'needs_labeling' => $runs->where('status', 'pending_labels')->count(),
                'unanalyzed_orthomosaics' => $orthomosaics->filter(fn ($o) => $o->classificationRuns->isEmpty())->count(),
            ];
        })->values();
    }

    // Furthest phase a project or a single AOI has genuinely reached --
    // deliberately the *furthest* reached, not a strict linear requirement,
    // since e.g. a redrawn AOI after a completed mission still counts as
    // having reached "mission." Index into the 5-step stepper the view
    // renders: AOI, Mission, Orthomosaic, Classify, Sample.
    public function computePhaseIndex($missions, $orthomosaics, $completedRuns, $points): int
    {
        $phaseIndex = 0;
        if ($missions->isNotEmpty()) $phaseIndex = 1;
        if ($orthomosaics->isNotEmpty()) $phaseIndex = 2;
        if ($completedRuns->isNotEmpty()) $phaseIndex = 3;
        if ($points->isNotEmpty()) $phaseIndex = 4;
        return $phaseIndex;
    }
}
