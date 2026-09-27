<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\AreaOfInterest;
use App\Models\AuditLog;
use App\Models\DroneMission;
use App\Models\Orthomosaic;
use App\Exceptions\AoiValidationException;
use App\Jobs\GenerateOrthomosaicPreviewJob;
use App\Services\Geo\AoiValidator;
use App\Services\OrthomosaicPreviewGenerator;
use App\Services\ProjectCardBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProjectController extends Controller
{
    public function create()
    {
        $user = Auth::user()->load('role');

        if (Project::where('created_by', $user->id)->count() >= Project::MAX_PER_USER) {
            return $this->limitReachedRedirect();
        }

        return view('projects.create', compact('user'));
    }

    /**
     * The full project list -- the Atlas lobby's rail only ever shows a
     * horizontal-scrolling strip (see DashboardController::lobby()), which
     * has no real limit but doesn't read as a browsable list once someone
     * has more projects than fit on screen. This is the same card data
     * (ProjectCardBuilder, shared with the lobby) in an actual wrapping
     * grid with status filters and name search, linked from the lobby's
     * "View all" control.
     */
    public function index(ProjectCardBuilder $cardBuilder)
    {
        $user = Auth::user()->load('role');

        $projects = $cardBuilder->loadForUser($user);
        $projectCards = $cardBuilder->build($projects, $user)->sortByDesc('updated_at_raw')->values();
        $projectLimit = Project::MAX_PER_USER;
        $atProjectLimit = $projectCards->where('is_creator', true)->count() >= $projectLimit;

        return view('projects.index', compact('user', 'projectCards', 'projectLimit', 'atProjectLimit'));
    }

    private function limitReachedRedirect(): RedirectResponse
    {
        return redirect()
            ->route('projects.index')
            ->with('error', 'You have reached the limit of ' . Project::MAX_PER_USER . ' projects. Delete one to create a new project.');
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (Project::where('created_by', $user->id)->count() >= Project::MAX_PER_USER) {
            return $this->limitReachedRedirect();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $project = Project::create([
            'created_by' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'active',
            'started_at' => now(),
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => 'lead',
            'assigned_at' => now(),
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    /**
     * Soft-archives (or restores) a project rather than deleting it — a
     * project can have AOIs, drone missions, orthomosaics, sampling points,
     * soil readings, and reports attached, so a hard delete would destroy
     * real field data. Archiving just hides it from the active lobby list
     * (the "Archived" filter chip already existed for this) and is fully
     * reversible. Only the project's creator can do this — matches how
     * other project-level actions (e.g. AOI confirm) already treat the
     * creator specially.
     */
    public function toggleArchive(Project $project)
    {
        $user = Auth::user();

        abort_unless($project->created_by === $user->id, 403);

        $newStatus = $project->status === 'archived' ? 'active' : 'archived';
        $project->update(['status' => $newStatus]);

        AuditLog::record(
            $newStatus === 'archived' ? 'archived_project' : 'restored_project',
            'projects',
            $project->id,
            ['project_id' => $project->id, 'project_name' => $project->name]
        );

        // Stays on whichever card list (lobby or All Projects) triggered
        // this, same reasoning as redirectIfArchived() below -- these
        // actions are only ever triggered from those two pages' "..."
        // menus, never from a project's own page.
        return redirect()
            ->to(url()->previous(route('researcher.lobby')))
            ->with('success', $newStatus === 'archived' ? 'Project archived.' : 'Project restored.');
    }

    public function rename(Request $request, Project $project): RedirectResponse
    {
        $user = Auth::user();

        // Same creator-only gate as archive/restore -- renaming is a
        // lighter action, but it still changes something every member
        // sees, so it stays with the same person who controls the
        // project's lifecycle rather than opening it to any member.
        abort_unless($project->created_by === $user->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $oldName = $project->name;
        $project->update(['name' => $validated['name']]);

        AuditLog::record(
            'renamed_project',
            'projects',
            $project->id,
            ['project_id' => $project->id, 'old_name' => $oldName, 'new_name' => $validated['name']]
        );

        return redirect()
            ->to(url()->previous(route('researcher.lobby')))
            ->with('success', 'Project renamed.');
    }

    // Permanent, unrecoverable delete -- only reachable from an already-
    // archived project (never directly from active), so archiving always
    // acts as a deliberate cooling-off step before anything is actually
    // destroyed. Cascading FK constraints (areas_of_interest -> drone_
    // missions/orthomosaics/suitability_surfaces/survey_comparisons ->
    // classification_runs -> classified_tiles/sampling_grids ->
    // sampling_points -> soil_readings, and projects -> reports) clean up
    // every DB row once $project->delete() runs, but cascades never touch
    // the filesystem -- every real file (orthomosaic GeoTIFFs, mission
    // KMZs, classification-run output directories, report files) has to be
    // deleted explicitly first, while the rows that hold their paths still
    // exist to read from.
    public function destroy(Project $project): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($project->created_by === $user->id, 403);
        abort_unless(
            $project->status === 'archived',
            422,
            'Only archived projects can be permanently deleted. Archive it first.'
        );

        foreach ($project->areasOfInterest as $aoi) {
            foreach ($aoi->droneMissions as $mission) {
                if ($mission->kmz_file_path) {
                    File::delete(storage_path('app/private/' . $mission->kmz_file_path));
                }
            }

            foreach ($aoi->orthomosaics as $orthomosaic) {
                if ($orthomosaic->file_path) {
                    File::delete(storage_path('app/private/' . $orthomosaic->file_path));
                }

                foreach ($orthomosaic->classificationRuns as $run) {
                    File::deleteDirectory(storage_path('app/private/classification-runs/' . $run->id));
                }
            }
        }

        foreach ($project->reports as $report) {
            if ($report->file_path) {
                File::delete(storage_path('app/private/' . $report->file_path));
            }
        }

        AuditLog::record('deleted_project', 'projects', $project->id, [
            'project_id' => $project->id,
            'project_name' => $project->name,
        ]);

        $project->delete();

        return redirect()
            ->to(url()->previous(route('researcher.lobby')))
            ->with('success', 'Project permanently deleted.');
    }

    public function show(Project $project)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        if ($redirect = $this->redirectIfArchived($project)) {
            return $redirect;
        }

        $project->loadCount([
            'areasOfInterest as aoi_count',
            'members as member_count',
        ]);

        $project->load([
            'areasOfInterest' => function ($query) {
                $query->with([
                    'droneMissions' => function ($missionQuery) {
                        $missionQuery->with(['orthomosaics.classificationRuns.samplingGrids.samplingPoints.soilReading']);
                    },
                ]);
            },
            'members.user',
        ]);

        $aoiCount = $project->areasOfInterest()->count();
        $droneMissionCount = $project->areasOfInterest->sum(fn ($aoi) => $aoi->droneMissions->count());
        $samplingPointCount = 0;
        $soilReadingCount = 0;

        foreach ($project->areasOfInterest as $aoi) {
            foreach ($aoi->droneMissions as $mission) {
                foreach ($mission->orthomosaics as $orthomosaic) {
                    foreach ($orthomosaic->classificationRuns as $run) {
                        foreach ($run->samplingGrids as $grid) {
                            $samplingPointCount += $grid->samplingPoints->count();
                            $soilReadingCount += $grid->samplingPoints->filter(fn ($point) => $point->soilReading !== null)->count();
                        }
                    }
                }
            }
        }

        $orthomosaicCount = $this->orthomosaicCount($project);
        $classificationCount = $this->classificationCount($project);
        $reportCount = $project->reports()->count();

        return view('projects.overview', compact('user', 'project', 'aoiCount', 'droneMissionCount', 'orthomosaicCount', 'classificationCount', 'samplingPointCount', 'soilReadingCount', 'reportCount'));
    }

    public function createSection(Project $project, ?string $section = null)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        if ($redirect = $this->redirectIfArchived($project)) {
            return $redirect;
        }

        // 'classification' is no longer a separate workflow step — uploading an
        // orthomosaic and classifying it are one step (the classification runs
        // automatically against the uploaded orthomosaic), so old links redirect
        // to the merged Orthomosaic section rather than 404ing.
        if ($section === 'classification') {
            return redirect()->route('projects.create-section', ['project' => $project, 'section' => 'orthomosaic']);
        }

        // 'drone-mission' is no longer a separate workflow step — drawing an
        // AOI and planning the flight over it are one step now (same
        // reasoning as the classification merge above), so old links
        // redirect to the merged Area & Mission section.
        if ($section === 'drone-mission') {
            return redirect()->route('projects.create-section', ['project' => $project, 'section' => 'aoi']);
        }

        $sectionMap = [
            'aoi' => ['title' => 'Area & Mission', 'subtitle' => 'Draw the survey boundary and plan the drone flight over it.', 'icon' => 'fa-draw-polygon'],
            'orthomosaic' => ['title' => 'Orthomosaic', 'subtitle' => 'Upload a new orthomosaic and run AI suitability classification for this project.', 'icon' => 'fa-layer-group'],
            'sampling-points' => ['title' => 'Sampling Points', 'subtitle' => 'Add new sampling points to this project.', 'icon' => 'fa-map-pin'],
            'soil-readings' => ['title' => 'Soil Readings', 'subtitle' => 'Record new soil readings for this project.', 'icon' => 'fa-flask'],
            'reports' => ['title' => 'Reports', 'subtitle' => 'Generate a new report for this project.', 'icon' => 'fa-file-lines'],
        ];

        $config = $sectionMap[$section ?? 'aoi'] ?? $sectionMap['aoi'];
        $currentRoute = $section ?? 'aoi';
        $metrics = $this->workspaceMetrics($project);

        if (! $this->sectionIsUnlocked($currentRoute, $metrics)) {
            return redirect()
                ->route('projects.section', ['project' => $project, 'section' => $this->firstIncompleteSection($metrics)])
                ->with('error', 'Complete and save the previous workflow step before opening this section.');
        }

        $items = collect();

        return view('projects.workspace-section', array_merge(
            compact('user', 'project', 'section', 'config', 'currentRoute', 'items'),
            $metrics
        ));
    }

    public function section(Project $project, string $section)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        if ($redirect = $this->redirectIfArchived($project)) {
            return $redirect;
        }

        // 'classification' is no longer a separate workflow step — see the
        // same redirect in createSection() for why.
        if ($section === 'classification') {
            return redirect()->route('projects.section', ['project' => $project, 'section' => 'orthomosaic']);
        }

        // 'drone-mission' is no longer a separate workflow step — see
        // createSection() for why.
        if ($section === 'drone-mission') {
            return redirect()->route('projects.section', ['project' => $project, 'section' => 'aoi']);
        }

        $sectionMap = [
            'aoi' => ['title' => 'Area & Mission', 'subtitle' => 'Draw the survey boundary and plan the drone flight over it.', 'icon' => 'fa-draw-polygon', 'emptyMessage' => 'No areas of interest defined yet for this project.', 'route' => 'aoi'],
            'orthomosaic' => ['title' => 'Orthomosaic', 'subtitle' => 'Review uploaded orthomosaics and their AI suitability classification for this project.', 'icon' => 'fa-layer-group', 'emptyMessage' => 'No orthomosaics have been uploaded for this project yet.', 'route' => 'orthomosaic'],
            'sampling-points' => ['title' => 'Sampling Points', 'subtitle' => 'Inspect sampling locations for this project.', 'icon' => 'fa-map-pin', 'emptyMessage' => 'No sampling points have been recorded for this project yet.', 'route' => 'sampling-points'],
            'soil-readings' => ['title' => 'Soil Readings', 'subtitle' => 'Review soil measurements collected for this project.', 'icon' => 'fa-flask', 'emptyMessage' => 'No soil readings have been recorded for this project yet.', 'route' => 'soil-readings'],
            'reports' => ['title' => 'Reports', 'subtitle' => 'Review generated reports for this project.', 'icon' => 'fa-file-lines', 'emptyMessage' => 'No reports have been generated for this project yet.', 'route' => 'reports'],
        ];

        $config = $sectionMap[$section] ?? $sectionMap['aoi'];
        $currentRoute = $config['route'] ?? 'aoi';
        $metrics = $this->workspaceMetrics($project);

        if (! $this->sectionIsUnlocked($currentRoute, $metrics)) {
            return redirect()
                ->route('projects.section', ['project' => $project, 'section' => $this->firstIncompleteSection($metrics)])
                ->with('error', 'Complete and save the previous workflow step before opening this section.');
        }

        $items = collect();

        if ($section === 'aoi') {
            $items = $project->areasOfInterest()->latest()->get();
        } elseif ($section === 'orthomosaic') {
            // Orthomosaic upload and its AI classification are one workflow
            // step now, so each orthomosaic item carries its classification
            // runs along with it (see workspace-section.blade.php) instead
            // of classification runs being a separate listed item type.
            $items = collect();
            foreach ($project->areasOfInterest()->with('droneMissions.orthomosaics.classificationRuns')->get() as $aoi) {
                foreach ($aoi->droneMissions as $mission) {
                    foreach ($mission->orthomosaics as $orthomosaic) {
                        $items->push($orthomosaic);
                    }
                }
            }
        } elseif ($section === 'sampling-points') {
            $items = collect();
            foreach ($project->areasOfInterest()->with('droneMissions.orthomosaics.classificationRuns.samplingGrids.samplingPoints')->get() as $aoi) {
                foreach ($aoi->droneMissions as $mission) {
                    foreach ($mission->orthomosaics as $orthomosaic) {
                        foreach ($orthomosaic->classificationRuns as $classificationRun) {
                            foreach ($classificationRun->samplingGrids as $grid) {
                                foreach ($grid->samplingPoints as $samplingPoint) {
                                    $items->push($samplingPoint);
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($section === 'soil-readings') {
            $items = collect();
            foreach ($project->areasOfInterest()->with('droneMissions.orthomosaics.classificationRuns.samplingGrids.samplingPoints.soilReading')->get() as $aoi) {
                foreach ($aoi->droneMissions as $mission) {
                    foreach ($mission->orthomosaics as $orthomosaic) {
                        foreach ($orthomosaic->classificationRuns as $classificationRun) {
                            foreach ($classificationRun->samplingGrids as $grid) {
                                foreach ($grid->samplingPoints as $samplingPoint) {
                                    if ($samplingPoint->soilReading !== null) {
                                        $items->push($samplingPoint->soilReading);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($section === 'reports') {
            $items = $project->reports()->latest()->get();
        }

        return view('projects.workspace-section', array_merge(
            compact('user', 'project', 'section', 'config', 'currentRoute', 'items'),
            $metrics
        ));
    }

    public function confirmAoi(Request $request, Project $project)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        $validated = $request->validate([
            'center_latitude' => ['required', 'numeric', 'between:-90,90'],
            'center_longitude' => ['required', 'numeric', 'between:-180,180'],
            'boundary_coordinates' => ['required', 'array', 'min:3'],
            'boundary_coordinates.*' => ['required', 'array', 'size:2'],
            'boundary_coordinates.*.0' => ['required', 'numeric', 'between:-90,90'],
            'boundary_coordinates.*.1' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $boundaryCoordinates = $this->closedBoundaryCoordinates($validated['boundary_coordinates']);

        try {
            $metrics = AoiValidator::validate($boundaryCoordinates);
        } catch (AoiValidationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $boundaryWkt = $this->boundaryCoordinatesToWkt($boundaryCoordinates);
        $aoi = $project->areasOfInterest()->where('status', 'active')->latest()->first();

        if ($aoi && $aoi->droneMissions()->where('status', 'completed')->exists()) {
            return response()->json([
                'message' => 'AOI can no longer be changed because the drone mission is completed.',
            ], 422);
        }

        $payload = [
            'project_id' => $project->id,
            'created_by' => $user->id,
            'name' => $project->name . ' AOI',
            'boundary_coordinates' => $boundaryCoordinates,
            'center_latitude' => $validated['center_latitude'],
            'center_longitude' => $validated['center_longitude'],
            'area_sqm' => round($metrics['area_m2'], 2),
            'status' => 'active',
            'notes' => sprintf(
                'Freehand AOI: %s m² (%s m longest dimension).',
                number_format($metrics['area_m2'], 0),
                number_format($metrics['longest_dimension_m'], 0)
            ),
        ];

        if ($aoi) {
            // The guard above already rules out completed missions, so
            // anything left here belongs to the boundary being replaced and
            // is no longer valid against the new shape — clear it out along
            // with its generated .kmz file so the Drone Mission step starts
            // fresh for the redrawn AOI.
            $staleMissions = $aoi->droneMissions()->get();

            foreach ($staleMissions as $staleMission) {
                if ($staleMission->kmz_file_path) {
                    File::delete(storage_path('app/private/' . $staleMission->kmz_file_path));
                }

                $staleMission->delete();
            }

            if ($staleMissions->isNotEmpty()) {
                AuditLog::record('reset_drone_missions_on_aoi_redraw', 'areas_of_interest', $aoi->id, [
                    'project_id' => $project->id,
                    'deleted_mission_ids' => $staleMissions->pluck('id')->all(),
                ]);
            }

            $aoi->update($payload);
        } else {
            $aoi = AreaOfInterest::create($payload);
        }

        $this->persistBoundaryPolygon($aoi, $boundaryWkt);

        AuditLog::record('confirmed_aoi', 'areas_of_interest', $aoi->id, [
            'project_id' => $project->id,
            'center_latitude' => $aoi->center_latitude,
            'center_longitude' => $aoi->center_longitude,
            'area_sqm' => $aoi->area_sqm,
            'boundary_coordinates' => $aoi->boundary_coordinates,
        ]);

        return response()->json([
            'message' => 'AOI confirmed. Proceed to Drone Mission to generate your flight plan.',
            'aoi' => [
                'id' => $aoi->id,
                'center_latitude' => $aoi->center_latitude,
                'center_longitude' => $aoi->center_longitude,
                'boundary_coordinates' => $aoi->boundary_coordinates,
                'area_sqm' => $aoi->area_sqm,
            ],
        ]);
    }

    /**
     * Clears the project's active AOI immediately when the user commits to
     * redrawing it (the "Redraw" confirmation in the UI), rather than
     * waiting until a replacement polygon is confirmed. Without this, an
     * abandoned redraw — the user navigates away or logs out after
     * confirming "Redraw" but before finishing/confirming a new shape —
     * would silently leave the old AOI fully intact, since nothing had
     * actually told the server the old one was being replaced. Deleting it
     * here means the project genuinely has no AOI from this point on, so an
     * abandoned redraw correctly shows up as empty rather than reverting to
     * stale data.
     */
    public function clearAoi(Request $request, Project $project)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        $aoi = $project->areasOfInterest()->where('status', 'active')->latest()->first();

        if (! $aoi) {
            return response()->json(['message' => 'No active AOI to clear.']);
        }

        if ($aoi->droneMissions()->where('status', 'completed')->exists()) {
            return response()->json([
                'message' => 'AOI can no longer be changed because the drone mission is completed.',
            ], 422);
        }

        foreach ($aoi->droneMissions()->get() as $mission) {
            if ($mission->kmz_file_path) {
                File::delete(storage_path('app/private/' . $mission->kmz_file_path));
            }
        }

        AuditLog::record('cleared_aoi_for_redraw', 'areas_of_interest', $aoi->id, [
            'project_id' => $project->id,
        ]);

        // Cascades to drone_missions/orthomosaics/suitability_surfaces/survey_comparisons
        // (all FK'd to areas_of_interest with onDelete('cascade')) — safe here because
        // the completed-mission guard above already rules out any AOI with real
        // post-flight data attached.
        $aoi->delete();

        return response()->json(['message' => 'AOI cleared. Draw a new Area of Interest.']);
    }

    /**
     * Stores an uploaded orthomosaic (.tif/.tiff) for the project's active
     * AOI. The file itself is stored privately (same convention as mission
     * KMZ files); georeferencing bounds are computed client-side (via
     * georaster.js reading the TIFF's embedded GeoKeys/tie points) and passed
     * along in the request, since PHP has no built-in GeoTIFF parsing.
     */
    public function uploadOrthomosaic(Request $request, Project $project)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        $validated = $request->validate([
            // max is in KB (Laravel convention for file rules) -- 500MB cap,
            // well above a real 4ha-AOI orthomosaic's typical size (~130MB
            // for the reference file used throughout testing) but bounded
            // so an oversized upload fails fast here instead of running
            // most of the way through classification before hitting that
            // job's own 300s timeout.
            'file' => ['required', 'file', 'mimes:tif,tiff', 'max:512000'],
            'bounds_coordinates' => ['nullable', 'array', 'size:4'],
            'bounds_coordinates.*' => ['numeric'],
            'coordinate_system' => ['nullable', 'string', 'max:50'],
        ]);

        $aoi = $project->areasOfInterest()->where('status', 'active')->latest()->first();

        if (! $aoi) {
            return response()->json(['message' => 'This project has no active Area of Interest yet.'], 422);
        }

        $mission = $aoi->droneMissions()->latest()->first();

        if (! $mission) {
            return response()->json(['message' => 'This project has no drone mission yet.'], 422);
        }

        $file = $validated['file'];
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'tif';
        $filename = $mission->id . '-' . now()->format('YmdHis') . '.' . $extension;
        $relativeDirectory = 'orthomosaics/' . $aoi->id;
        $relativePath = $relativeDirectory . '/' . $filename;

        File::ensureDirectoryExists(storage_path('app/private/' . $relativeDirectory));
        $file->move(storage_path('app/private/' . $relativeDirectory), $filename);

        $orthomosaic = Orthomosaic::create([
            'aoi_id' => $aoi->id,
            'mission_id' => $mission->id,
            'uploaded_by' => $user->id,
            'file_path' => $relativePath,
            'file_format' => $extension,
            'bounds_coordinates' => $validated['bounds_coordinates'] ?? null,
            'coordinate_system' => $validated['coordinate_system'] ?? 'WGS84',
            'status' => 'ready',
            'uploaded_at' => now(),
        ]);

        AuditLog::record('uploaded_orthomosaic', 'orthomosaics', $orthomosaic->id, [
            'project_id' => $project->id,
            'aoi_id' => $aoi->id,
            'mission_id' => $mission->id,
            'file_path' => $relativePath,
        ]);

        // Off the request thread -- the researcher's upload response
        // shouldn't wait on this (see GenerateOrthomosaicPreviewJob's own
        // docblock). The map already shows an instant client-side preview
        // from the file the browser just parsed for upload; this job is
        // only for what a *later* page load displays.
        GenerateOrthomosaicPreviewJob::dispatch($orthomosaic)->onQueue('classification');

        return response()->json([
            'message' => 'Orthomosaic uploaded.',
            'orthomosaic' => [
                'id' => $orthomosaic->id,
                'file_url' => route('projects.orthomosaic.file', ['project' => $project->id, 'orthomosaic' => $orthomosaic->id]),
                'remove_url' => route('projects.orthomosaic.remove', ['project' => $project->id, 'orthomosaic' => $orthomosaic->id]),
                'bounds_coordinates' => $orthomosaic->bounds_coordinates,
                'uploaded_at' => $orthomosaic->uploaded_at?->format('M d, Y g:i A'),
            ],
        ]);
    }

    /**
     * Streams a stored orthomosaic's raw file bytes back to the browser so
     * it can be re-parsed and re-rendered (via georaster.js) when the
     * Orthomosaic section is revisited after the initial upload.
     */
    public function downloadOrthomosaicFile(Project $project, Orthomosaic $orthomosaic)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        abort_unless($orthomosaic->areaOfInterest?->project_id === $project->id, 404);

        $absolutePath = storage_path('app/private/' . $orthomosaic->file_path);

        abort_unless(File::exists($absolutePath), 404);

        return response()->file($absolutePath, ['Content-Type' => 'image/tiff']);
    }

    /**
     * Serves the small downscaled preview PNG (see OrthomosaicPreviewGenerator)
     * instead of the full-resolution GeoTIFF -- what the workspace map
     * actually displays on every page load after the initial upload. 404s if
     * the background job hasn't produced one yet (a fresh upload, or one
     * from before this feature existed); the frontend falls back to the
     * original full-file rendering in that case.
     */
    public function orthomosaicPreview(Project $project, Orthomosaic $orthomosaic, OrthomosaicPreviewGenerator $generator)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        abort_unless($orthomosaic->areaOfInterest?->project_id === $project->id, 404);

        abort_unless($generator->previewExists($orthomosaic), 404);

        return response()->file($generator->previewAbsolutePath($orthomosaic), ['Content-Type' => 'image/png']);
    }

    /**
     * Deletes a stored orthomosaic (file + DB row) so the user can clear a
     * bad/wrong upload and try again. Analysis isn't built yet, so this is
     * the only lifecycle action available on an uploaded orthomosaic today.
     */
    public function removeOrthomosaic(Project $project, Orthomosaic $orthomosaic)
    {
        $user = Auth::user()->load('role');

        abort_unless(
            $project->created_by === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403
        );

        abort_unless($orthomosaic->areaOfInterest?->project_id === $project->id, 404);

        $absolutePath = storage_path('app/private/' . $orthomosaic->file_path);

        if (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }

        AuditLog::record('removed_orthomosaic', 'orthomosaics', $orthomosaic->id, [
            'project_id' => $project->id,
            'file_path' => $orthomosaic->file_path,
        ]);

        $orthomosaic->delete();

        return response()->json(['message' => 'Orthomosaic removed.']);
    }

    /**
     * Blocks page-load access to an archived project's workspace — archiving
     * is meant to take a project out of active use, so it shouldn't still be
     * openable via a direct/bookmarked URL. Returns null (no redirect) when
     * the project isn't archived, so callers can early-return only when this
     * yields something.
     */
    private function redirectIfArchived(Project $project): ?RedirectResponse
    {
        if ($project->status !== 'archived') {
            return null;
        }

        // Sends the user back wherever they came from (the lobby or the
        // All Projects page) instead of always the lobby, so the message
        // below actually lands somewhere it's rendered -- both pages show
        // flashed 'error' now. Falls back to the lobby if there's no
        // previous request (direct/bookmarked URL).
        return redirect()
            ->to(url()->previous(route('researcher.lobby')))
            ->with('error', 'This project is archived. Use its "..." menu to restore it.');
    }

    private function closedBoundaryCoordinates(array $coordinates): array
    {
        $closed = array_map(fn (array $point) => [(float) $point[0], (float) $point[1]], $coordinates);
        $first = $closed[0];
        $last = $closed[array_key_last($closed)];

        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $closed[] = $first;
        }

        return $closed;
    }

    private function boundaryCoordinatesToWkt(array $coordinates): string
    {
        $points = array_map(
            fn (array $point) => sprintf('%.8F %.8F', (float) $point[1], (float) $point[0]),
            $coordinates
        );

        return 'POLYGON((' . implode(', ', $points) . '))';
    }

    private function persistBoundaryPolygon(AreaOfInterest $aoi, string $boundaryWkt): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $aoi->update(['boundary_polygon' => $boundaryWkt]);

            return;
        }

        try {
            DB::update(
                'UPDATE areas_of_interest SET boundary_polygon = ?::geometry WHERE id = ?',
                ['SRID=4326;' . $boundaryWkt, $aoi->id]
            );
        } catch (\Throwable) {
            // Some PostgreSQL installs used in development do not have PostGIS
            // functions/types available. The app still persists the editable AOI
            // through boundary_coordinates, so do not fail the whole save here.
        }
    }

    private function workspaceMetrics(Project $project): array
    {
        return [
            'aoiCount' => $project->areasOfInterest()->where('status', 'active')->count(),
            'droneMissionCount' => $this->droneMissionCount($project),
            'orthomosaicCount' => $this->orthomosaicCount($project),
            'classificationCount' => $this->classificationCount($project),
            'samplingPointCount' => $this->samplingPointCount($project),
            'soilReadingCount' => $this->soilReadingCount($project),
            'reportCount' => $project->reports()->count(),
        ];
    }

    private function sectionIsUnlocked(string $section, array $metrics): bool
    {
        return match ($section) {
            'aoi' => true,
            // Drawing an AOI and planning the drone flight over it are one
            // step now (both live on the 'aoi' route) — Orthomosaic still
            // gates on a mission actually existing, just generated from
            // that same page instead of a separate one.
            'orthomosaic' => $metrics['droneMissionCount'] > 0,
            // Orthomosaic upload + classification are one step now (the
            // classification is meant to run automatically against the
            // uploaded orthomosaic), so Sampling Points unlocks off the
            // orthomosaic count rather than a separate classification gate.
            'sampling-points' => $metrics['orthomosaicCount'] > 0,
            'soil-readings' => $metrics['samplingPointCount'] > 0,
            'reports' => $metrics['soilReadingCount'] > 0,
            default => false,
        };
    }

    private function firstIncompleteSection(array $metrics): string
    {
        foreach (['aoi', 'orthomosaic', 'sampling-points', 'soil-readings', 'reports'] as $section) {
            if (! $this->sectionIsUnlocked($section, $metrics)) {
                return match ($section) {
                    'orthomosaic' => 'aoi',
                    'sampling-points' => 'orthomosaic',
                    'soil-readings' => 'sampling-points',
                    'reports' => 'soil-readings',
                    default => 'aoi',
                };
            }
        }

        return 'reports';
    }

    private function droneMissionCount(Project $project): int
    {
        return $project->areasOfInterest()->withCount('droneMissions')->get()->sum('drone_missions_count');
    }

    private function orthomosaicCount(Project $project): int
    {
        return $project->areasOfInterest()
            ->with(['droneMissions' => fn ($query) => $query->withCount('orthomosaics')])
            ->get()
            ->flatMap(fn ($aoi) => $aoi->droneMissions)
            ->sum('orthomosaics_count');
    }

    private function classificationCount(Project $project): int
    {
        $count = 0;

        foreach ($project->areasOfInterest()->with('droneMissions.orthomosaics.classificationRuns')->get() as $aoi) {
            foreach ($aoi->droneMissions as $mission) {
                foreach ($mission->orthomosaics as $orthomosaic) {
                    $count += $orthomosaic->classificationRuns->count();
                }
            }
        }

        return $count;
    }

    private function samplingPointCount(Project $project): int
    {
        $count = 0;

        foreach ($project->areasOfInterest()->with('droneMissions.orthomosaics.classificationRuns.samplingGrids.samplingPoints')->get() as $aoi) {
            foreach ($aoi->droneMissions as $mission) {
                foreach ($mission->orthomosaics as $orthomosaic) {
                    foreach ($orthomosaic->classificationRuns as $run) {
                        foreach ($run->samplingGrids as $grid) {
                            $count += $grid->samplingPoints->count();
                        }
                    }
                }
            }
        }

        return $count;
    }

    private function soilReadingCount(Project $project): int
    {
        $count = 0;

        foreach ($project->areasOfInterest()->with('droneMissions.orthomosaics.classificationRuns.samplingGrids.samplingPoints.soilReading')->get() as $aoi) {
            foreach ($aoi->droneMissions as $mission) {
                foreach ($mission->orthomosaics as $orthomosaic) {
                    foreach ($orthomosaic->classificationRuns as $run) {
                        foreach ($run->samplingGrids as $grid) {
                            $count += $grid->samplingPoints->filter(fn ($point) => $point->soilReading !== null)->count();
                        }
                    }
                }
            }
        }

        return $count;
    }
}
