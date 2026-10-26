<?php

namespace App\Http\Controllers;

use App\Models\AreaOfInterest;
use App\Models\Orthomosaic;
use App\Models\Project;
use App\Models\SurveyComparison;
use App\Services\SurveyComparisonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Lets a researcher pick two orthomosaics of the same AreaOfInterest --
 * from different drone flights, i.e. different points in time -- and see
 * how land cover / suitability changed between them. Builds on
 * SurveyComparisonService for the actual cell-matching computation; this
 * controller is just auth + orchestration + rendering.
 */
class SurveyComparisonController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorizeProject($project);

        $aois = $project->areasOfInterest()
            ->with(['droneMissions.orthomosaics.classificationRuns' => function ($query) {
                $query->where('status', 'completed')->latest('completed_at');
            }])
            ->get();

        $comparableOrthomosaicsByAoi = $aois->mapWithKeys(function ($aoi) {
            $orthomosaics = $aoi->droneMissions
                ->flatMap(fn ($mission) => $mission->orthomosaics)
                ->filter(fn (Orthomosaic $orthomosaic) => $orthomosaic->classificationRuns->isNotEmpty())
                ->sortByDesc('uploaded_at')
                ->values();

            return [$aoi->id => $orthomosaics];
        })->filter(fn ($orthomosaics) => $orthomosaics->count() >= 2);

        $comparisons = SurveyComparison::whereIn('aoi_id', $aois->pluck('id'))
            ->with(['areaOfInterest', 'orthomosaicA', 'orthomosaicB', 'generatedBy'])
            ->latest('compared_at')
            ->get();

        return view('projects.comparisons.index', [
            'user' => Auth::user(),
            'project' => $project,
            'currentRoute' => 'comparisons',
            'aois' => $aois,
            'comparableOrthomosaicsByAoi' => $comparableOrthomosaicsByAoi,
            'comparisons' => $comparisons,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($project);

        $validated = $request->validate([
            'orthomosaic_a_id' => ['required', 'uuid'],
            'orthomosaic_b_id' => ['required', 'uuid', 'different:orthomosaic_a_id'],
        ]);

        $earlier = Orthomosaic::findOrFail($validated['orthomosaic_a_id']);
        $later = Orthomosaic::findOrFail($validated['orthomosaic_b_id']);

        abort_unless($earlier->areaOfInterest?->project_id === $project->id, 404);
        abort_unless($later->areaOfInterest?->project_id === $project->id, 404);
        abort_unless($earlier->aoi_id === $later->aoi_id, 422, 'Both orthomosaics must belong to the same area of interest.');

        // "Earlier"/"later" is by real capture order, not by which dropdown
        // the user happened to pick first -- swap if submitted backwards so
        // change_summary always reads as a real before/after timeline.
        if ($earlier->uploaded_at && $later->uploaded_at && $earlier->uploaded_at->gt($later->uploaded_at)) {
            [$earlier, $later] = [$later, $earlier];
        }

        $comparison = app(SurveyComparisonService::class)->compare($earlier, $later, Auth::user());

        return redirect()->route('projects.comparisons.show', [$project, $comparison]);
    }

    /**
     * Trend across an AOI's whole survey history, not just one pair --
     * chains SurveyComparisonService::findOrCompare() across every
     * consecutive pair of classified orthomosaics in capture order, reusing
     * the same matching engine show()/store() already rely on rather than
     * a separate N-way comparison algorithm.
     */
    public function timeline(Project $project, AreaOfInterest $aoi): View
    {
        $this->authorizeProject($project);
        abort_unless($aoi->project_id === $project->id, 404);

        $orthomosaics = $aoi->orthomosaics()
            ->whereHas('classificationRuns', fn ($q) => $q->where('status', 'completed'))
            ->with(['classificationRuns' => fn ($q) => $q->where('status', 'completed')->latest('completed_at')])
            ->orderBy('uploaded_at')
            ->get();

        $points = [];

        if ($orthomosaics->count() >= 3) {
            $service = app(SurveyComparisonService::class);

            foreach ($orthomosaics->sliding(2) as $pair) {
                [$earlier, $later] = $pair->values();
                $comparison = $service->findOrCompare($earlier, $later, Auth::user());
                $summary = $comparison->change_summary ?? [];

                $points[] = [
                    'date' => $later->uploaded_at?->format('M d, Y') ?? 'Unknown date',
                    'comparison_id' => $comparison->id,
                    'avg_suitability_after' => $summary['average_suitability_score_after'] ?? null,
                    'improved' => $summary['transition_counts']['improved'] ?? 0,
                    'declined' => $summary['transition_counts']['declined'] ?? 0,
                    'unchanged' => $summary['transition_counts']['unchanged'] ?? 0,
                ];
            }
        }

        return view('projects.comparisons.timeline', [
            'project' => $project,
            'aoi' => $aoi,
            'orthomosaicCount' => $orthomosaics->count(),
            'points' => $points,
        ]);
    }

    public function show(Project $project, SurveyComparison $comparison): View
    {
        $this->authorizeProject($project);
        abort_unless($comparison->areaOfInterest?->project_id === $project->id, 404);

        $comparison->load(['areaOfInterest', 'orthomosaicA', 'orthomosaicB', 'generatedBy']);

        $runA = $comparison->orthomosaicA->classificationRuns()->where('status', 'completed')->latest('completed_at')->first();
        $runB = $comparison->orthomosaicB->classificationRuns()->where('status', 'completed')->latest('completed_at')->first();

        return view('projects.comparisons.show', [
            'project' => $project,
            'comparison' => $comparison,
            'runA' => $runA,
            'runB' => $runB,
        ]);
    }

    private function authorizeProject(Project $project): void
    {
        $user = Auth::user();
        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );
    }
}
