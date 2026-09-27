<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectCardBuilder;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function admin()
    {
        $currentUser = Auth::user();

        $users = User::with('role')->get();

        $stats = [
            'total'            => $users->count(),
            'pending'          => $users->where('status', 'pending')->count(),
            'active'           => $users->where('is_active', true)->count(),
            'researchers'      => $users->filter(fn ($u) => optional($u->role)->name === 'researcher')->count(),
            'field_technicians' => $users->filter(fn ($u) => optional($u->role)->name === 'field_technician')->count(),
        ];

        $pendingUsers = $users->where('status', 'pending')->sortByDesc('created_at')->take(5)->values();

        $recentActivity = AuditLog::with('user')->orderByDesc('logged_at')->take(6)->get();

        $adminNavRoute = 'dashboard';
        $adminPendingCount = $stats['pending'];

        return view('dashboard.admin', compact('currentUser', 'stats', 'pendingUsers', 'recentActivity', 'adminNavRoute', 'adminPendingCount'));
    }

    public function lobby(ProjectCardBuilder $cardBuilder)
    {
        $user = Auth::user()->load('role');

        // Deep eager load so every stat below is computed from data already
        // in memory -- no N+1 queries per project/card despite the lobby
        // needing to reach all the way down to sampling points to answer
        // "how far has this project actually gotten." Same load + card
        // shape ProjectController::index() (the full "All Projects" page)
        // uses, via ProjectCardBuilder, so both read identical data.
        $projects = $cardBuilder->loadForUser($user);
        $phaseLabels = ProjectCardBuilder::PHASE_LABELS;
        $projectCards = $cardBuilder->build($projects, $user);
        $projectLimit = Project::MAX_PER_USER;
        $atProjectLimit = $projectCards->where('is_creator', true)->count() >= $projectLimit;

        // "Your overall work" -- a real, computed summary of this specific
        // user's output across every project they touch, not just a count
        // of projects. Built from the same already-loaded tree above, so
        // no extra queries.
        // Every soil reading across every project's sampling points -- used
        // for both the raw "collected" count and the suitability-rate
        // quality metric below. Computed once, shared by both, from the
        // same already-loaded tree (soilReading eager-loaded above).
        $allSoilReadings = $projects->flatMap->areasOfInterest
            ->flatMap->orthomosaics
            ->flatMap->classificationRuns
            ->flatMap->samplingGrids
            ->flatMap->samplingPoints
            ->map->soilReading
            ->filter()
            ->values();
        $ratedSoilReadings = $allSoilReadings->whereNotNull('suitability_rating');

        $workSummary = [
            'projects' => $projectCards->count(),
            'area_hectares' => round($projects->flatMap->areasOfInterest->sum('area_sqm') / 10000, 1),
            'aois_mapped' => $projects->flatMap->areasOfInterest->count(),
            'missions_flown' => $projects->flatMap->areasOfInterest->flatMap->droneMissions->count(),
            'orthomosaics_uploaded' => $projectCards->sum('orthomosaic_count'),
            'classifications_completed' => $projects->flatMap->areasOfInterest->flatMap->orthomosaics->flatMap->classificationRuns->where('status', 'completed')->count(),
            'sampling_points' => $projectCards->sum('point_count'),
            'soil_readings_collected' => $allSoilReadings->count(),
            'reports_generated' => $projects->flatMap->reports->count(),
            'comparisons_run' => $projects->flatMap->areasOfInterest->flatMap->surveyComparisons->count(),
            // Readings with no rating yet are excluded from the denominator
            // (not counted as unsuitable) -- null when nothing's been rated
            // yet, rendered as "--" rather than a misleading 0%.
            'suitability_rate' => $ratedSoilReadings->count() > 0
                ? round($ratedSoilReadings->where('suitability_rating', 'suitable')->count() / $ratedSoilReadings->count() * 100)
                : null,
        ];

        // Rehabilitation progress -- aggregates every SurveyComparison's
        // change_summary (see SurveyComparisonService) across every project,
        // the cross-project version of the same "is this site actually
        // improving" signal the Compare Surveys page shows for one AOI.
        $allComparisons = $projects->flatMap(function (Project $project) {
            return $project->areasOfInterest->flatMap->surveyComparisons->map(function ($comparison) use ($project) {
                $comparison->setRelation('project', $project);
                return $comparison;
            });
        })->sortByDesc('compared_at')->values();

        $rehabProgress = [
            'comparisons_run' => $allComparisons->count(),
            'cells_improved' => $allComparisons->sum(fn ($c) => $c->change_summary['transition_counts']['improved'] ?? 0),
            'cells_declined' => $allComparisons->sum(fn ($c) => $c->change_summary['transition_counts']['declined'] ?? 0),
            'cells_unchanged' => $allComparisons->sum(fn ($c) => $c->change_summary['transition_counts']['unchanged'] ?? 0),
        ];

        $recentComparisons = $allComparisons->take(4)->map(function ($comparison) {
            return [
                'id' => $comparison->id,
                'project_id' => $comparison->project->id,
                'aoi_name' => $comparison->areaOfInterest->name ?? 'Unnamed area',
                'compared_at' => $comparison->compared_at?->diffForHumans(),
                'improved' => $comparison->change_summary['transition_counts']['improved'] ?? 0,
                'declined' => $comparison->change_summary['transition_counts']['declined'] ?? 0,
            ];
        })->values();

        // Fieldwork panel: same field researchers the Sampling Points
        // assignment dropdown offers (created by this researcher, still
        // active), each with how many of the points in these projects are
        // currently assigned to / already collected by them.
        $allPoints = $projects->flatMap->areasOfInterest
            ->flatMap->orthomosaics
            ->flatMap->classificationRuns
            ->where('status', 'completed')
            ->flatMap->samplingGrids
            ->flatMap->samplingPoints;

        $fieldTeam = User::whereHas('role', fn ($q) => $q->where('name', 'field_technician'))
            ->where('approved_by', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($tech) use ($allPoints) {
                $theirs = $allPoints->where('assigned_to', $tech->id);
                return [
                    'name' => $tech->name,
                    'assigned' => $theirs->where('status', 'transmitted')->count(),
                    'collected' => $theirs->where('status', 'collected')->count(),
                ];
            })->values();

        $fieldProgress = [
            'total' => $allPoints->count(),
            'unassigned' => $allPoints->where('status', 'pending')->count(),
            'assigned' => $allPoints->where('status', 'transmitted')->count(),
            'collected' => $allPoints->where('status', 'collected')->count(),
        ];

        // Projects with something genuinely waiting on this user -- a
        // labeling step left mid-way, or an orthomosaic uploaded and never
        // analyzed. Surfaced separately from the general grid so it reads
        // as "do this next," not buried among everything else.
        $needsAttention = $projectCards->filter(fn ($p) => $p['needs_labeling'] > 0 || $p['unanalyzed_orthomosaics'] > 0)->values();

        // One point/polygon per AOI across every project, for a single
        // cross-project overview map -- real boundaries, not a stand-in
        // graphic, colored by how far each AOI has actually progressed.
        $aoiMapPoints = $projects->flatMap(function (Project $project) use ($phaseLabels, $cardBuilder) {
            return $project->areasOfInterest->map(function ($aoi) use ($project, $phaseLabels, $cardBuilder) {
                $missions = $aoi->droneMissions;
                $orthomosaics = $aoi->orthomosaics;
                $completedRuns = $orthomosaics->flatMap->classificationRuns->where('status', 'completed');
                $points = $completedRuns->flatMap->samplingGrids->flatMap->samplingPoints;
                $phaseIndex = $cardBuilder->computePhaseIndex($missions, $orthomosaics, $completedRuns, $points);

                $boundary = (is_array($aoi->boundary_coordinates) && count($aoi->boundary_coordinates) >= 3)
                    ? $aoi->boundary_coordinates
                    : null;

                if (!$boundary && (!$aoi->center_latitude || !$aoi->center_longitude)) {
                    return null;
                }

                // This AOI's own land-cover split (not the cross-project
                // one below) -- feeds the map marker's pie-chart rendering
                // so each pin shows what's actually at that site, not just
                // a flat phase color. All-zero when nothing's classified
                // yet, so the marker can fall back to a plain dot instead
                // of an empty pie.
                $aoiGrids = $completedRuns->flatMap->samplingGrids;
                $aoiTotalCells = $aoiGrids->count();
                $aoiCoverCounts = $aoiGrids->countBy('dominant_cover');
                $coverBreakdown = collect(['bare_soil', 'vegetation', 'open_water'])
                    ->mapWithKeys(fn ($cover) => [
                        $cover => $aoiTotalCells > 0 ? round($aoiCoverCounts->get($cover, 0) / $aoiTotalCells * 100, 1) : 0,
                    ]);

                // Atlas hover-card fields -- this AOI's own area/suitability/
                // point count (distinct from the cross-project $workSummary
                // totals above), same soil-reading relation already
                // eager-loaded for $allSoilReadings, so no extra queries.
                $aoiRatedReadings = $points->map->soilReading->filter()->whereNotNull('suitability_rating');

                return [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'aoi_name' => $aoi->name ?: 'Unnamed AOI',
                    'area_hectares' => round(($aoi->area_sqm ?? 0) / 10000, 1),
                    'point_count' => $points->count(),
                    'suitability_rate' => $aoiRatedReadings->count() > 0
                        ? round($aoiRatedReadings->where('suitability_rating', 'suitable')->count() / $aoiRatedReadings->count() * 100)
                        : null,
                    'boundary' => $boundary,
                    'center' => [$aoi->center_latitude, $aoi->center_longitude],
                    'phase_index' => $phaseIndex,
                    'phase_label' => $phaseLabels[$phaseIndex],
                    'phase_labels' => $phaseLabels,
                    'cover_breakdown' => $coverBreakdown,
                ];
            });
        })->filter()->values();

        // Land-cover breakdown across every completed classification run --
        // the finalized per-cell label (dominant_cover already reflects the
        // water-detection override and any manual correction), not the raw
        // cluster vote. Order matches the suitability tiering already
        // finalized in the classification pipeline: bare soil (suitable) ->
        // vegetation (marginal) -> open water (excluded).
        $allGrids = $projects->flatMap->areasOfInterest
            ->flatMap->orthomosaics
            ->flatMap->classificationRuns
            ->where('status', 'completed')
            ->flatMap->samplingGrids;
        $coverCounts = $allGrids->countBy('dominant_cover');
        $totalCells = $allGrids->count();

        // Land-cover names, not suitability-tier names -- matches the
        // overlay legend (RunClassificationJob) and ReportDataAggregator.
        // This widget's own waffle-* swatch colors (below, in this file's
        // CSS) are a deliberate app-brand palette, not a pixel-match to
        // the overlay image -- there's no orthomosaic shown alongside this
        // aggregate, cross-project view for a mismatch to be visible
        // against, unlike the Orthomosaic map's own legend or the Reports
        // page's mini-map + chart.
        $suitabilityBreakdown = collect([
            'bare_soil' => 'Bare Soil',
            'vegetation' => 'Vegetation',
            'open_water' => 'Open Water',
        ])->map(function ($label, $cover) use ($coverCounts, $totalCells) {
            $count = $coverCounts->get($cover, 0);
            return [
                'cover' => $cover,
                'label' => $label,
                'count' => $count,
                'percent' => $totalCells > 0 ? round($count / $totalCells * 100, 1) : 0,
            ];
        })->values();

        // A fixed-size grid of colored squares for the "waffle" visual --
        // largest-remainder rounding so the square counts always sum to
        // exactly $waffleSize even though percentages rarely divide evenly.
        // Below 100 real cells, one square = one real cell (never invents
        // resolution the data doesn't have); at or above 100, squares
        // become proportional samples.
        $waffleSize = min(100, $totalCells);
        $suitabilityWaffle = [];
        if ($waffleSize > 0) {
            $shares = $suitabilityBreakdown->map(function ($row) use ($totalCells, $waffleSize) {
                $exact = $totalCells > 0 ? ($row['count'] / $totalCells) * $waffleSize : 0;
                return ['cover' => $row['cover'], 'floor' => (int) floor($exact), 'remainder' => $exact - floor($exact)];
            });

            $allocated = $shares->sum('floor');
            $remaining = $waffleSize - $allocated;
            $shares = $shares->sortByDesc('remainder')->values();

            foreach ($shares as $i => $share) {
                $extra = $i < $remaining ? 1 : 0;
                for ($n = 0; $n < $share['floor'] + $extra; $n++) {
                    $suitabilityWaffle[] = $share['cover'];
                }
            }
        }

        return view('dashboard.lobby', compact(
            'user',
            'projectCards',
            'projectLimit',
            'atProjectLimit',
            'workSummary',
            'needsAttention',
            'fieldTeam',
            'fieldProgress',
            'aoiMapPoints',
            'suitabilityBreakdown',
            'suitabilityWaffle',
            'totalCells',
            'rehabProgress',
            'recentComparisons'
        ));
    }
}
