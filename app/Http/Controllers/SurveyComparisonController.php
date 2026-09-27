<?php

namespace App\Http\Controllers;

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
