@php
    // Computed early (and NOT re-derived further down -- this is the single
    // source of truth) so the <head> can decide whether to load the
    // Leaflet/Turf/georaster stack at all. Those libraries are only needed
    // on routes that actually render a map panel.
    // 'overview' gets the map itself (#workspace-map + libraries) via
    // $showMapStack, but NOT $isAoiRoute -- that flag drives the AOI
    // toolbar/info-panel/empty-state prompt, which stay exclusive to the
    // real 'aoi' route. Overview's map is read-only: satellite basemap +
    // confirmed AOI boundary if one exists, no drawing tools.
    //
    // $showMissionPanel (the flag that gates the mission-planning UI) is
    // NOT set here -- drawing the AOI and planning the drone flight over it
    // are one workflow step now (one route, 'aoi'), so that flag is computed
    // further down once $activeAoi is known (true once an AOI is confirmed
    // on this route), not from $currentRoute the way route flags are here.
    $currentRoute = $currentRoute ?? 'overview';
    $isAoiRoute = $currentRoute === 'aoi';
    $isOrthomosaicRoute = $currentRoute === 'orthomosaic';
    // Read-only map view: the same orthomosaic + suitability overlay the
    // Orthomosaic route shows, plus the generated sampling points on top --
    // no upload/analyze controls, just "where are they and how many."
    $isSamplingPointsRoute = $currentRoute === 'sampling-points';
    // No map needed here -- just a data-entry form + list, so it's left out
    // of $showMapStack (no reason to load Leaflet/georaster for this route).
    // Read-only map view, same as Sampling Points: the orthomosaic +
    // sampling points, this time colored by soil suitability rating
    // instead of collection status, with each point's full reading in its
    // popup. No entry form -- readings arrive from the mobile app now.
    $isSoilReadingsRoute = $currentRoute === 'soil-readings';
    // Still no map here -- this route is a data dashboard (charts), not a
    // spatial view.
    $isReportsRoute = $currentRoute === 'reports';
    // Compare Surveys renders its own two lightweight read-only Leaflet
    // maps (suitability overlay only, no drawing/upload tools), so it only
    // needs bare Leaflet -- not the full draw/georaster/turf stack that
    // $showMapStack pulls in for the AOI/Orthomosaic workflow routes.
    $isComparisonsRoute = $currentRoute === 'comparisons';
    $showMapStack = $isAoiRoute || $isOrthomosaicRoute || $isSamplingPointsRoute || $isSoilReadingsRoute || $currentRoute === 'overview';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Project Workspace') | Project M.A.N.G.R.O.V.E</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/favicon-badge.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/apple-touch-icon.png') }}">

    <script>
        (function () {
            var savedTheme = localStorage.getItem('workspaceTheme');
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    @if ($showMapStack || $isReportsRoute || $isComparisonsRoute)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
            crossorigin="" />
    @endif
    @if ($showMapStack)
        <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    @endif

    @vite(['resources/css/workspace.css', 'resources/js/workspace/lite.js'])
    @if ($showMapStack)
        @vite(['resources/js/workspace/map.js'])
    @endif
    @if ($isReportsRoute)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha512-k72Y+sws0148dhTBT8Nyro04RwjANbBxNCZ80xKT3FdHNON55/us15Ju0YQ2dD0uzyBJ5YjXnbUcLWaCaw3rCg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        @vite(['resources/js/workspace/reports.js'])
    @endif

    @include('partials.page-transition-styles')
    @include('partials.motion-styles')

</head>
<body>
    @include('partials.page-transition')
    @include('partials.logout-modal')
    @php
        $workspaceUser = $user ?? auth()->user();
        $workspaceUserName = $workspaceUser?->name ?? 'Guest User';
        $workspaceUserAvatarUrl = $workspaceUser?->avatar_url ?? null;
        $workspaceRoleName = optional($workspaceUser?->role)->name ?? 'Viewer';
        $projectName = $project->name ?? 'Untitled Project';
        $projectStatus = $project->status ?? 'active';
        $createdDate = isset($project?->created_at) ? $project->created_at->format('M d, Y') : 'Pending';
        $currentRoute = $currentRoute ?? 'overview';
        $projectIsPersisted = isset($project) && !empty($project->id) && ($project->exists ?? false);
        $aoiTotal = $aoiCount ?? ($project->aoi_count ?? ($projectIsPersisted && method_exists($project, 'areasOfInterest') ? $project->areasOfInterest()->count() : 0));
        $missionTotal = $droneMissionCount ?? 0;
        $orthomosaicTotal = $orthomosaicCount ?? 0;
        $samplingTotal = $samplingPointCount ?? 0;
        $soilTotal = $soilReadingCount ?? 0;
        $reportTotal = $reportCount ?? 0;
        $activeAoi = $projectIsPersisted && method_exists($project, 'areasOfInterest')
            ? $project->areasOfInterest()->where('status', 'active')->latest()->first()
            : null;
        // True once an AOI is confirmed on the merged 'aoi' route -- this is
        // what gates every mission-planning UI block (Mission Parameters,
        // the flight-path legend, Mission Summary), all rendered into the
        // DOM from initial page load (just `hidden`) so confirming an AOI
        // can reveal them in place via JS, no page reload.
        $showMissionPanel = $isAoiRoute && (bool) $activeAoi;
        $activeAoiHasCompletedMission = $activeAoi
            ? $activeAoi->droneMissions()->where('status', 'completed')->exists()
            : false;
        $savedAoiConfig = $activeAoi ? [
            'id' => $activeAoi->id,
            'center_latitude' => $activeAoi->center_latitude,
            'center_longitude' => $activeAoi->center_longitude,
            'boundary_coordinates' => $activeAoi->boundary_coordinates,
            'area_sqm' => $activeAoi->area_sqm,
        ] : null;
        $aoiTotal = $activeAoi ? max(1, (int) $aoiTotal) : (int) $aoiTotal;
        $activeMission = $activeAoi ? $activeAoi->droneMissions()->latest()->first() : null;
        // Only fetched/serialized on the route that actually reads it
        // (map-workspace.js / drone-mission-panel.js both gate on
        // config.savedMission) -- otherwise this queries the missionRequest
        // relation and embeds a potentially large waypoints array into every
        // Overview/Orthomosaic page load for data those pages never touch.
        $activeMissionRequest = $isAoiRoute ? $activeMission?->missionRequest : null;
        $savedMissionConfig = ($activeMissionRequest && is_array($activeMissionRequest->waypoints) && count($activeMissionRequest->waypoints) > 0) ? [
            'waypoints' => $activeMissionRequest->waypoints,
            'waypoint_count' => $activeMissionRequest->waypoint_count,
            'total_distance_meters' => $activeMissionRequest->total_distance_meters,
            'line_spacing_meters' => $activeMissionRequest->line_spacing_meters,
            'waypoint_speed_mps' => $activeMissionRequest->waypoint_speed_mps,
            // The rest of the actual generation inputs -- restored into the
            // Mission Parameters form on load so it reflects what this
            // mission was really generated with, not just the currently
            // selected drone's defaults (which would silently mismatch the
            // waypoints/summary already shown alongside it).
            'drone_key' => $activeMissionRequest->drone_key,
            'altitude' => $activeMissionRequest->altitude,
            'overlap_percent' => $activeMissionRequest->overlap_percent,
            'gimbal_angle' => $activeMissionRequest->gimbal_angle,
            'finish_action' => $activeMissionRequest->finish_action,
            'execute_rc_lost_action' => $activeMissionRequest->execute_rc_lost_action,
            'transitional_speed_mps' => $activeMissionRequest->transitional_speed_mps,
            'download_url' => route('missions.download', $activeMissionRequest),
        ] : null;
        // Global toggle, admin-controlled (Settings > Advanced mission
        // settings) -- on by default so this week's field testing can tune
        // finish-action/RC-lost-action/transitional-speed freely; meant to
        // be turned off once good defaults are locked in for deployment.
        // Gated behind $showMissionPanel (same flag the panel itself uses)
        // rather than queried unconditionally -- $showMissionPanel is false
        // whenever $project isn't a real, persisted Eloquent record (e.g.
        // ProjectWorkspaceOverviewTest renders this layout against a
        // hand-built, unsaved Project with no database at all), so this
        // never issues a query in a context with no app_settings table.
        $showAdvancedMissionSettings = $showMissionPanel && \App\Models\Setting::get('show_advanced_mission_settings', true);
        // Same admin-toggle mechanism -- default true (locked to Davao
        // Occidental, the real deployment area) so this never accidentally
        // ships open; flip off in /admin/settings to draw an AOI and
        // upload/analyze an orthomosaic anywhere else (e.g. sample imagery
        // from outside the province).
        $restrictMapToDavaoOccidental = \App\Models\Setting::get('restrict_map_to_davao_occidental', true);
        // Separate from the Davao restriction above -- this specifically
        // controls whether confirming an AOI boxes the map into staying
        // near it (final-deployment behavior) or leaves it free to pan
        // elsewhere (needed when a test orthomosaic's real location doesn't
        // overlap the AOI it's attached to).
        $lockMapToAoi = \App\Models\Setting::get('lock_map_to_aoi', true);
        $activeOrthomosaic = $activeMission ? $activeMission->orthomosaics()->latest()->first() : null;
        // Small downscaled PNG (see OrthomosaicPreviewGenerator) instead of
        // the full-resolution GeoTIFF -- null until the background job that
        // generates it finishes (or for an orthomosaic uploaded before this
        // existed), in which case map-workspace.js falls back to the
        // original full-file client-side rendering.
        $orthomosaicPreviewGenerator = app(\App\Services\OrthomosaicPreviewGenerator::class);
        $existingOrthomosaicConfig = $activeOrthomosaic ? [
            'id' => $activeOrthomosaic->id,
            'file_url' => route('projects.orthomosaic.file', ['project' => $project->id, 'orthomosaic' => $activeOrthomosaic->id]),
            'preview_url' => $orthomosaicPreviewGenerator->previewExists($activeOrthomosaic)
                ? route('projects.orthomosaic.preview', ['project' => $project->id, 'orthomosaic' => $activeOrthomosaic->id])
                : null,
            'remove_url' => route('projects.orthomosaic.remove', ['project' => $project->id, 'orthomosaic' => $activeOrthomosaic->id]),
            'bounds_coordinates' => $activeOrthomosaic->bounds_coordinates,
            'uploaded_at' => $activeOrthomosaic->uploaded_at?->format('M d, Y g:i A'),
            'classify_url' => route('classification.run', $activeOrthomosaic->id),
        ] : null;

        // Properties panel, Orthomosaic route only -- real file/geo
        // metadata about the uploaded raster itself (distinct from the
        // AOI's own info panel, since the orthomosaic's rectangular
        // footprint is usually larger than the AOI polygon it was flown
        // for -- photogrammetry tools pad a diagonal/irregular AOI out to
        // a rectangle). File size read directly off the actual file on
        // disk (a filesize() stat call, not reading file content) since
        // no size column is stored; footprint area computed the same way
        // AoiValidator/MissionGridGenerator compute real polygon area
        // (PolygonGeometry::toLocalMeters + area), not a naive lat/lng
        // degree multiplication, so it's accurate.
        $orthomosaicDetails = null;
        if ($activeOrthomosaic && $isOrthomosaicRoute) {
            $b = $activeOrthomosaic->bounds_coordinates;
            $footprintAreaHa = null;
            if (is_array($b) && count($b) === 4) {
                [$minLat, $minLng, $maxLat, $maxLng] = $b;
                $corners = [[$minLat, $minLng], [$minLat, $maxLng], [$maxLat, $maxLng], [$maxLat, $minLng]];
                $local = \App\Services\Geo\PolygonGeometry::toLocalMeters($corners);
                $footprintAreaHa = round(\App\Services\Geo\PolygonGeometry::area($local['points']) / 10000, 2);
            }

            $filePath = storage_path('app/private/' . $activeOrthomosaic->file_path);
            $fileSizeBytes = \Illuminate\Support\Facades\File::exists($filePath) ? \Illuminate\Support\Facades\File::size($filePath) : null;

            $orthomosaicDetails = [
                'file_format' => $activeOrthomosaic->file_format,
                'file_size' => $fileSizeBytes !== null ? number_format($fileSizeBytes / 1048576, 1) . ' MB' : null,
                'resolution_cm' => $activeOrthomosaic->resolution_cm,
                'coordinate_system' => $activeOrthomosaic->coordinate_system,
                'footprint_area_ha' => $footprintAreaHa,
                'bounds' => is_array($b) && count($b) === 4
                    ? number_format($b[0], 5) . ', ' . number_format($b[1], 5) . ' to ' . number_format($b[2], 5) . ', ' . number_format($b[3], 5)
                    : null,
                'captured_at' => $activeOrthomosaic->captured_at?->format('M d, Y'),
                'uploaded_at' => $activeOrthomosaic->uploaded_at?->format('M d, Y g:i A'),
                'uploaded_by' => $activeOrthomosaic->uploader?->name,
            ];
        }

        // Classification is a separate, later action on an uploaded
        // orthomosaic (not part of upload itself), so this is its own
        // latest-run lookup rather than piggybacking on $activeOrthomosaic.
        $activeClassificationRun = $activeOrthomosaic ? $activeOrthomosaic->classificationRuns()->latest()->first() : null;
        $existingSuitabilitySurface = ($activeClassificationRun && $activeClassificationRun->status === 'completed')
            ? $activeClassificationRun->suitabilitySurfaces()->latest()->first()
            : null;
        // soilReading.collector eager-loaded here (not a separate query) so
        // the Soil Readings route's map popups can show a point's full
        // reading without a second round-trip -- cheap to always include,
        // even on routes that don't use it.
        $existingSamplingPoints = $activeClassificationRun
            ? \App\Models\SamplingPoint::whereIn('grid_id', $activeClassificationRun->samplingGrids()->pluck('id'))
                ->with('soilReading.collector')
                ->orderBy('priority_rank')
                ->get()
            : collect();
        $existingClassificationConfig = $activeClassificationRun ? [
            'id' => $activeClassificationRun->id,
            'status' => $activeClassificationRun->status,
            'status_url' => route('classification.status', $activeClassificationRun->id),
            'cancel_url' => $activeClassificationRun->status === 'processing'
                ? route('classification.cancel', $activeClassificationRun->id)
                : null,
            'clusters_url' => route('classification.clusters', $activeClassificationRun->id),
            'labels_url' => route('classification.labels', $activeClassificationRun->id),
            'sampling_points_url' => route('classification.sampling-points', $activeClassificationRun->id),
            'grid_cells_url' => route('classification.grid-cells', $activeClassificationRun->id),
            'overlay_url' => $existingSuitabilitySurface ? route('classification.overlay', $activeClassificationRun->id) : null,
            'legend' => $existingSuitabilitySurface?->legend,
            // Reconstructs the same "X suitable cell(s), Y excluded" summary
            // the live analysis flow shows right after a run finishes (see
            // resultsInfoEl in map-workspace.js) -- without this, reopening
            // an already-completed orthomosaic left that line blank (a bare
            // checkmark with no text), since the restore-on-load path never
            // had this number to show.
            'score_summary' => $activeClassificationRun->status === 'completed' ? [
                'eligible_count' => $activeClassificationRun->samplingGrids()->whereNotNull('suitability_score')->count(),
                'excluded_count' => $activeClassificationRun->samplingGrids()->whereNull('suitability_score')->count(),
            ] : null,
            'points' => $existingSamplingPoints->map(fn ($point) => [
                'id' => $point->id,
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'priority_rank' => $point->priority_rank,
                'status' => $point->status,
                'assigned_to' => $point->assigned_to,
                'reading' => $point->soilReading ? [
                    'species' => $point->soilReading->species,
                    'species_label' => \App\Services\SpeciesReference::label($point->soilReading->species),
                    'species_photo_url' => \App\Services\SpeciesReference::photoUrl($point->soilReading->species),
                    'ph_avg' => $point->soilReading->ph_avg,
                    'salinity_avg' => $point->soilReading->salinity_avg,
                    'moisture_avg' => $point->soilReading->moisture_avg,
                    'nitrogen_avg' => $point->soilReading->nitrogen_avg,
                    'phosphorus_avg' => $point->soilReading->phosphorus_avg,
                    'potassium_avg' => $point->soilReading->potassium_avg,
                    'suitability_rating' => $point->soilReading->suitability_rating,
                    'collected_by' => $point->soilReading->collector?->name,
                    'collected_at' => $point->soilReading->collected_at?->format('M d, Y g:i A'),
                ] : null,
            ])->values(),
        ] : null;

        // Sampling Points route: who this researcher can assign points to.
        // Field technician accounts have no project-membership row (see
        // SamplingPointAssignmentController's own comment on this) --
        // approved_by is the closest real ownership link the schema has.
        $fieldTechnicians = $isSamplingPointsRoute
            ? \App\Models\User::whereHas('role', fn ($q) => $q->where('name', 'field_technician'))
                ->where('approved_by', $workspaceUser?->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : collect();

        // Soil Readings route data -- reuses $existingSamplingPoints (already
        // fetched above, no extra query) rather than re-querying. Readings
        // arrive via the mobile app (Api\MobileSyncController) now, not a
        // web form, so this is read-only display data.
        // samplingPoint eager-loaded (not just collector) so the Table
        // View's Coordinates column doesn't lazy-load per row -- was
        // already being accessed lazily for priority_rank in the chip
        // list below, a real pre-existing N+1 this fixes at the same time.
        $collectedSoilReadings = $activeClassificationRun
            ? \App\Models\SoilReading::whereIn('point_id', $existingSamplingPoints->pluck('id'))
                ->with(['collector', 'samplingPoint'])
                ->latest('collected_at')
                ->get()
            : collect();

        // Reports route data -- generation itself needs a completed
        // classification run (see ReportController::generate()); this
        // mirrors that same check just to explain why the button might be
        // disabled, not to duplicate the actual authorization logic.
        $canGenerateReport = $activeClassificationRun?->status === 'completed';
        $existingReports = $isReportsRoute ? $project->reports()->latest()->get() : collect();
        // Same aggregator ReportController::generate() uses for the PDF --
        // one computation, both renderers, so the live dashboard and the
        // downloaded PDF never disagree about these numbers.
        $reportsChartData = ($isReportsRoute && $activeClassificationRun)
            ? app(\App\Services\ReportDataAggregator::class)->summarize($activeClassificationRun)
            : null;

        $droneModels = collect(config('drones', []))
            ->map(fn ($model, $key) => array_merge(['key' => $key], $model, ['name' => $model['label'] ?? $key]))
            ->values()
            ->all();

        $stepState = [
            // Drawing the AOI and planning the drone flight over it are one
            // step now -- 'aoi' covers both, so it's still "complete" as
            // soon as an AOI is confirmed (matching Orthomosaic's own lock,
            // which still gates on a mission existing, not on this chip).
            'aoi' => $aoiTotal > 0 ? 'complete' : 'in-progress',
            // Orthomosaic upload and its AI classification are one workflow
            // step — classification runs automatically against the uploaded
            // orthomosaic rather than being a separate manual step.
            'orthomosaic' => $orthomosaicTotal > 0 ? 'complete' : ($missionTotal > 0 ? 'in-progress' : 'pending'),
            'sampling-points' => $samplingTotal > 0 ? 'complete' : ($orthomosaicTotal > 0 ? 'in-progress' : 'pending'),
            'soil-readings' => $soilTotal > 0 ? 'complete' : ($samplingTotal > 0 ? 'in-progress' : 'pending'),
            'reports' => $reportTotal > 0 ? 'complete' : ($soilTotal > 0 ? 'in-progress' : 'pending'),
        ];

        $workflowSteps = [
            ['short' => 'Area & Mission', 'label' => 'Area & Mission', 'route' => 'aoi', 'status' => $stepState['aoi']],
            ['short' => 'Orthomosaic', 'label' => 'Orthomosaic', 'route' => 'orthomosaic', 'status' => $stepState['orthomosaic']],
            ['short' => 'Samples', 'label' => 'Sampling Points', 'route' => 'sampling-points', 'status' => $stepState['sampling-points']],
            ['short' => 'Soil', 'label' => 'Soil Readings', 'route' => 'soil-readings', 'status' => $stepState['soil-readings']],
            ['short' => 'Reports', 'label' => 'Reports', 'route' => 'reports', 'status' => $stepState['reports']],
        ];

        $overviewUrl = isset($project) && !empty($project->id)
            ? url('/projects/' . $project->id)
            : route('researcher.lobby');

        $navItems = [
            ['label' => 'Overview', 'icon' => 'fa-gauge-high', 'route' => 'overview', 'url' => $overviewUrl, 'locked' => false],
            ['label' => 'Area & Mission', 'icon' => 'fa-draw-polygon', 'route' => 'aoi', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/aoi') : '#', 'locked' => false],
            ['label' => 'Orthomosaic', 'icon' => 'fa-layer-group', 'route' => 'orthomosaic', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/orthomosaic') : '#', 'locked' => $missionTotal < 1],
            ['label' => 'Sampling Points', 'icon' => 'fa-map-pin', 'route' => 'sampling-points', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/sampling-points') : '#', 'locked' => $orthomosaicTotal < 1],
            ['label' => 'Soil Readings', 'icon' => 'fa-flask', 'route' => 'soil-readings', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/soil-readings') : '#', 'locked' => $samplingTotal < 1],
            ['label' => 'Reports', 'icon' => 'fa-file-lines', 'route' => 'reports', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/reports') : '#', 'locked' => $soilTotal < 1],
            // Not part of the sequential AOI -> Orthomosaic -> ... workflow
            // above (no single "complete" state -- a project can always
            // gain more orthomosaics over time), so it unlocks off a raw
            // count instead of the previous step's completion. Own
            // controller/routes (SurveyComparisonController), not the
            // generic ProjectController::section() renderer the other nav
            // items use.
            ['label' => 'Compare Surveys', 'icon' => 'fa-code-compare', 'route' => 'comparisons', 'url' => isset($project) && !empty($project->id) ? route('projects.comparisons.index', $project) : '#', 'locked' => $orthomosaicTotal < 2],
        ];

        foreach ($navItems as &$navItem) {
            $navItem['complete'] = ($stepState[$navItem['route']] ?? null) === 'complete';
        }
        unset($navItem);
    @endphp

    <div
        id="workspaceRoot"
        {{-- Broader than $showMissionPanel on purpose: Alpine needs to bind
             the Mission Parameters form's directives at initial page load
             (while it's still hidden pre-AOI-confirm) so revealing it later
             via a plain `hidden` toggle works without a page reload. --}}
        @if ($isAoiRoute) x-data="droneMissionPanel(window.workspaceConfig)" x-init="init()" @endif
    >
        <div class="gis-titlebar">
            <div class="toolbar-side">
                <div class="toolbar-actions">
                    <a href="{{ route('researcher.lobby') }}" class="icon-btn" title="Back to Lobby">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                </div>
                <div class="brand-cluster">
                    <span class="brand-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project M.A.N.G.R.O.V.E logo"></span>
                    <div class="brand-copy">
                        <span class="app-name">Project M.A.N.G.R.O.V.E</span>
                        <span class="toolbar-divider">/</span>
                        <span class="project-name">{{ $projectName }}</span>
                    </div>
                </div>
            </div>

            <div class="toolbar-side">
                @include('partials.notification-bell')

                <div class="user-cluster">
                    <button class="icon-btn" id="userMenuToggle" type="button" title="Menu" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div class="user-menu-dropdown" id="userMenuDropdown" hidden>
                        <div class="user-menu-profile">
                            <div class="user-avatar">
                                @if ($workspaceUserAvatarUrl)
                                    <img src="{{ $workspaceUserAvatarUrl }}" alt="{{ $workspaceUserName }}">
                                @else
                                    {{ strtoupper(substr($workspaceUserName, 0, 1)) }}
                                @endif
                            </div>
                            <div class="user-menu-profile-text">
                                <span class="user-name">{{ $workspaceUserName }}</span>
                                <span class="role-badge">{{ $workspaceRoleName }}</span>
                            </div>
                        </div>

                        <div class="user-menu-divider"></div>

                        <button class="user-menu-item" id="themeToggle" type="button">
                            <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                            <span id="themeToggleLabel">Switch to dark mode</span>
                        </button>
                        <button class="user-menu-item" id="soundToggle" type="button">
                            <i class="fa-solid fa-volume-high" id="soundToggleIcon"></i>
                            <span id="soundToggleText">Mute interface sounds</span>
                        </button>
                        <a href="{{ route('settings.account') }}" class="user-menu-item">
                            <i class="fa-solid fa-gear"></i>
                            <span>Account Settings</span>
                        </a>

                        <div class="user-menu-divider"></div>

                        <form method="POST" action="{{ url('/logout') }}" style="margin:0;" data-loading-label="Logging out...">
                            @csrf
                            <button type="submit" class="user-menu-item user-menu-item-danger" onclick="return openLogoutModal(event)">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <nav class="gis-ribbon" aria-label="Workspace ribbon">
            <div class="ribbon-tabs" role="tablist">
                @foreach ($navItems as $item)
                    @php
                        $tabInProgress = ($stepState[$item['route']] ?? null) === 'in-progress' && !($item['complete'] ?? false) && !$item['locked'];
                        $tabTooltip = $item['locked'] ? $item['label'] . ' is locked until the previous step is confirmed' : $item['label'];
                    @endphp
                    <a
                        href="{{ $item['locked'] ? '#' : ($item['url'] ?? '#') }}"
                        class="ribbon-tab {{ $currentRoute === $item['route'] ? 'active' : '' }} {{ $item['locked'] ? 'locked' : '' }}"
                        title="{{ $tabTooltip }}"
                        aria-disabled="{{ $item['locked'] ? 'true' : 'false' }}"
                        data-route="{{ $item['route'] }}"
                        data-url="{{ $item['url'] ?? '#' }}"
                        data-workflow-route="{{ $item['route'] }}"
                    >
                        <i class="fa-solid {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                        @if ($item['locked'])
                            <i class="fa-solid fa-lock lock-icon"></i>
                        @elseif ($item['complete'] ?? false)
                            <i class="fa-solid fa-circle-check nav-check" aria-hidden="true" title="Completed"></i>
                        @elseif ($tabInProgress)
                            <span class="tab-dot" aria-hidden="true"></span>
                        @endif
                    </a>
                @endforeach
            </div>

        </nav>

        @php $showFullProjectInfo = $currentRoute === 'overview'; @endphp
        <main class="map-shell {{ $isAoiRoute && !$activeAoi ? 'aoi-placement-mode' : '' }}" id="mapShell">
            {{-- Left dock = tools (things you DO): layers + whatever action
                 panel belongs to the current step. Right dock = properties
                 (things that REPORT state): project info + results. Skipped
                 on Overview (nothing route-specific to control there yet)
                 and on Reports (no map on that route at all -- the Layers
                 toggles for Basemap/AOI/Orthomosaic/Grid would sit there
                 doing nothing, since there's no #workspace-map underneath
                 them to affect). --}}
            @if ($showMapStack && $currentRoute !== 'overview')
                <aside class="dock dock-left" id="workspaceSidebar">
                    <div class="dock-header">
                        <span class="dock-header-icon"><i class="fa-solid fa-toolbox"></i></span>
                        <span class="dock-header-text">Workspace Tools</span>
                        <button class="dock-collapse-btn" type="button" data-collapse-target="workspaceSidebar" title="Toggle tools">
                            <i class="fa-solid fa-angle-left"></i>
                        </button>
                    </div>
                    <div class="dock-scroll {{ $isAoiRoute ? 'dock-scroll--aoi' : '' }}">
                        <section class="dock-section" id="layersPanel">
                            <div class="dock-section-title">
                                <i class="fa-solid fa-map-location-dot"></i> <span>Layers</span>
                                <button type="button" class="dock-section-toggle" aria-expanded="true" title="Collapse Layers">
                                    <i class="fa-solid fa-angle-down"></i>
                                </button>
                            </div>
                            <div class="dock-section-body">
                                <ul class="layer-list">
                                    <li class="layer-item">
                                        <span class="layer-check checked"><i class="fa-solid fa-check"></i></span>
                                        <span class="layer-swatch layer-swatch--basemap"></span>
                                        <span class="layer-label">Satellite Basemap</span>
                                    </li>
                                    @if ($activeAoi)
                                        <li class="layer-item">
                                            <span class="layer-check checked"><i class="fa-solid fa-check"></i></span>
                                            <span class="layer-swatch layer-swatch--aoi"></span>
                                            <span class="layer-label">AOI Boundary</span>
                                        </li>
                                    @endif
                                    @if ($existingOrthomosaicConfig)
                                        <li class="layer-item">
                                            <span class="layer-check checked"><i class="fa-solid fa-check"></i></span>
                                            <span class="layer-swatch layer-swatch--ortho"></span>
                                            <span class="layer-label">Orthomosaic Raster</span>
                                        </li>
                                    @endif
                                    @if ($isOrthomosaicRoute)
                                        {{-- Present in DOM from page load (not gated by @if on the
                                             surface itself) so submitting labels in the same session --
                                             no reload -- can reveal this immediately, same pattern as
                                             #aoiRedrawSection/#missionParametersPanel. --}}
                                        <li class="layer-item layer-item--toggle" id="suitabilityLayerToggle" role="button" tabindex="0" aria-pressed="true" title="Toggle the suitability overlay" {{ $existingSuitabilitySurface ? '' : 'hidden' }}>
                                            <span class="layer-check checked" id="suitabilityLayerCheck"><i class="fa-solid fa-check"></i></span>
                                            <span class="layer-swatch layer-swatch--suitability"></span>
                                            <span class="layer-label">Suitability Overlay</span>
                                        </li>
                                    @endif
                                    @if ($isSamplingPointsRoute || $isSoilReadingsRoute)
                                        {{-- Present from page load, same reasoning as the suitability
                                             toggle above -- a freshly-generated batch of points can
                                             appear mid-session without a reload. --}}
                                        <li class="layer-item layer-item--toggle" id="samplingPointsLayerToggle" role="button" tabindex="0" aria-pressed="true" title="Toggle sampling point pins" {{ $samplingTotal > 0 ? '' : 'hidden' }}>
                                            <span class="layer-check checked" id="samplingPointsLayerCheck"><i class="fa-solid fa-check"></i></span>
                                            <span class="layer-swatch layer-swatch--points"></span>
                                            <span class="layer-label">Point Pins</span>
                                        </li>
                                    @endif
                                    <li class="layer-item layer-item--toggle" id="gridLayerToggle" role="button" tabindex="0" aria-pressed="false" title="Overlay a reference grid to gauge area/distance on the map">
                                        <span class="layer-check" id="gridLayerCheck"></span>
                                        <span class="layer-swatch layer-swatch--grid"></span>
                                        <span class="layer-label">Reference Grid</span>
                                        <span class="layer-meta" id="gridLayerMeta" hidden></span>
                                    </li>
                                </ul>
                            </div>
                        </section>

                        @if ($isSamplingPointsRoute)
                            <section class="dock-section" id="samplingPointsPanel">
                                <div class="dock-section-title">
                                    <i class="fa-solid fa-map-pin"></i> <span>Sampling Points</span>
                                    <button type="button" class="dock-section-toggle" aria-expanded="true" title="Collapse Sampling Points">
                                        <i class="fa-solid fa-angle-down"></i>
                                    </button>
                                </div>
                                <div class="dock-section-body">
                                    @if ($samplingTotal > 0)
                                        <div class="project-stat-tile">
                                            <span class="project-stat-value">{{ $samplingTotal }}</span>
                                            <span class="project-stat-label">{{ Str::plural('Point', $samplingTotal) }} Generated</span>
                                        </div>
                                        <p class="aoi-metric-hint">Numbered markers on the map show each point's location, ordered by priority, over the scored suitability overlay. Marker color shows collection status: <span class="status-dot status-dot--pending"></span> pending, <span class="status-dot status-dot--transmitted"></span> assigned, <span class="status-dot status-dot--collected"></span> collected.</p>

                                        <div class="sampling-assign-block">
                                            @if ($fieldTechnicians->isEmpty())
                                                <p class="aoi-metric-hint">No field researcher accounts yet — you need one before you can assign points to a mobile device.</p>
                                            @else
                                                <label for="samplingAssignTechnician" class="sampling-assign-label">Assign to field researcher</label>
                                                <select id="samplingAssignTechnician" class="mission-select">
                                                    <option value="" disabled selected>Select a technician</option>
                                                    @foreach ($fieldTechnicians as $technician)
                                                        <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                                                    @endforeach
                                                </select>
                                                <select id="samplingAssignPoint" class="mission-select">
                                                    <option value="" disabled selected>Select a point</option>
                                                </select>
                                                <button type="button" class="mission-generate" id="samplingAssignBtn" disabled>
                                                    <i class="fa-solid fa-paper-plane"></i>
                                                    <span>Assign &amp; Send to Device</span>
                                                </button>
                                                <button type="button" class="mission-generate" id="samplingAssignAllBtn" disabled>
                                                    <i class="fa-solid fa-people-arrows"></i>
                                                    <span>Assign All Points to This Technician</span>
                                                </button>
                                                <p class="sampling-assign-status" id="samplingAssignStatus" hidden></p>
                                            @endif
                                        </div>
                                    @elseif (($existingClassificationConfig['status'] ?? null) === 'pending_labels')
                                        <p class="aoi-metric-hint">Classification's first pass is done and waiting on cluster labels before it can score cells and generate points — finish that on the Orthomosaic step.</p>
                                    @elseif (($existingClassificationConfig['status'] ?? null) === 'processing')
                                        <p class="aoi-metric-hint">Classification is still running on the Orthomosaic step. Sampling points appear here once it finishes and cells are scored.</p>
                                    @elseif ($existingOrthomosaicConfig)
                                        <p class="aoi-metric-hint">No sampling points yet — run AI classification on the Orthomosaic step first. Only bare-soil cells (the highest suitability score) generate points.</p>
                                    @else
                                        <p class="aoi-metric-hint">Upload an orthomosaic and run classification on the Orthomosaic step first.</p>
                                    @endif
                                </div>
                            </section>
                        @endif

                        @if ($isSoilReadingsRoute)
                            <section class="dock-section" id="soilReadingsPanel">
                                <div class="dock-section-title">
                                    <i class="fa-solid fa-flask"></i> <span>Soil Readings</span>
                                    <button type="button" class="dock-section-toggle" aria-expanded="true" title="Collapse Soil Readings">
                                        <i class="fa-solid fa-angle-down"></i>
                                    </button>
                                </div>
                                <div class="dock-section-body">
                                    @if ($collectedSoilReadings->isEmpty())
                                        <p class="aoi-metric-hint">
                                            @if ($existingSamplingPoints->isEmpty())
                                                No sampling points exist for this project yet — generate them from the Orthomosaic step first.
                                            @else
                                                No soil readings have been synced from the field yet. Readings are collected via the mobile app and appear here automatically once submitted.
                                            @endif
                                        </p>
                                    @else
                                        <div class="project-stat-tile">
                                            <span class="project-stat-value">{{ $collectedSoilReadings->count() }}</span>
                                            <span class="project-stat-label">{{ Str::plural('Reading', $collectedSoilReadings->count()) }} Collected</span>
                                        </div>
                                        <div class="soil-readings-view-toggle" role="group" aria-label="View mode">
                                            <button type="button" class="view-toggle-btn active" id="soilReadingsMapViewBtn" data-view="map">
                                                <i class="fa-solid fa-map"></i> Map View
                                            </button>
                                            <button type="button" class="view-toggle-btn" id="soilReadingsTableViewBtn" data-view="table">
                                                <i class="fa-solid fa-table"></i> Table View
                                            </button>
                                        </div>
                                        <p class="aoi-metric-hint">Pin color shows soil suitability: <span class="status-dot status-dot--suitable"></span> suitable, <span class="status-dot status-dot--marginal"></span> marginal, <span class="status-dot status-dot--unsuitable"></span> unsuitable, <span class="status-dot status-dot--pending"></span> not yet collected. Click a pin on the map for its full reading, or switch to Table View for every reading at once.</p>
                                    @endif
                                </div>
                            </section>
                        @endif

                        @if ($isAoiRoute)
                            <section class="dock-section" id="aoiToolbar" {{ $activeAoi ? 'hidden' : '' }}>
                                <div class="dock-section-title">
                                    <i class="fa-solid fa-draw-polygon"></i> <span>Draw</span>
                                    <button type="button" class="dock-section-toggle" aria-expanded="true" title="Collapse Draw">
                                        <i class="fa-solid fa-angle-down"></i>
                                    </button>
                                </div>
                                <div class="dock-section-body">
                                    <div class="dock-toolbar-btns">
                                        <button type="button" class="aoi-toolbar-btn active" data-aoi-mode="polygon">
                                            <i class="fa-solid fa-draw-polygon"></i> Polygon
                                        </button>
                                        <button type="button" class="aoi-toolbar-btn" data-aoi-mode="rectangle">
                                            <i class="fa-solid fa-vector-square"></i> Rectangle
                                        </button>
                                        <button type="button" class="aoi-toolbar-btn" data-aoi-mode="corridor">
                                            <i class="fa-solid fa-route"></i> Corridor
                                        </button>
                                    </div>
                                    <div class="aoi-corridor-width" id="aoiCorridorWidth" hidden>
                                        <label for="corridorWidthInput">Corridor width (m)</label>
                                        <input type="number" id="corridorWidthInput" min="10" max="150" step="5" value="50">
                                    </div>
                                </div>
                            </section>

                            <section class="dock-section" id="aoiRedrawSection" hidden>
                                <div class="dock-section-title">
                                    <i class="fa-solid fa-list-check"></i> <span>AOI Actions</span>
                                    <button type="button" class="dock-section-toggle" aria-expanded="true" title="Collapse AOI Actions">
                                        <i class="fa-solid fa-angle-down"></i>
                                    </button>
                                </div>
                                <div class="dock-section-body">
                                    <button class="mission-generate" type="button" id="confirmAoiBtn" hidden>
                                        <i class="fa-solid fa-check"></i>
                                        <span>Confirm AOI</span>
                                    </button>
                                    <p class="mission-lock-notice">
                                        <i class="fa-solid fa-circle-info"></i>
                                        Redraws clear this AOI and any drone mission generated for it.
                                    </p>
                                    <button class="mission-generate mission-generate--warning" type="button" id="repositionAoiBtn">
                                        <i class="fa-solid fa-arrows-rotate"></i>
                                        <span>Redraw AOI</span>
                                    </button>
                                </div>
                            </section>

                            {{-- Revealed once the AOI above is confirmed --
                                 same present-in-DOM-but-hidden pattern as
                                 #aoiRedrawSection, toggled by the same
                                 setAoiReadOnly()/startDrawing() JS so the
                                 AOI-confirm-to-mission-planning transition
                                 happens in place, no page reload. --}}
                            <section class="dock-section" id="missionParametersPanel" x-cloak hidden>
                                <div class="dock-section-title">
                                    <i class="fa-solid fa-helicopter"></i> <span>Mission Parameters</span>
                                    <button type="button" class="dock-section-toggle" aria-expanded="true" title="Collapse Mission Parameters">
                                        <i class="fa-solid fa-angle-down"></i>
                                    </button>
                                </div>
                                <form class="dock-section-body" @submit.prevent="generateMission" data-no-page-transition>
                                    <div class="mission-form-row">
                                        <label for="droneModel">Aircraft</label>
                                        <select class="mission-select" id="droneModel" x-model.number="droneModelIndex" required>
                                            <template x-for="(model, index) in droneModels" :key="model.key">
                                                <option :value="index" x-text="model.name"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <p class="mission-lock-notice">
                                        <i class="fa-solid fa-circle-info"></i>
                                        Values default to the selected drone's baseline.
                                    </p>

                                    <div class="mission-grid">
                                        <div class="mission-form-row">
                                            <label for="missionAltitude">Altitude (m)</label>
                                            <input class="mission-input" id="missionAltitude" type="number" x-model.number="altitude" min="10" max="500" step="1" required>
                                        </div>
                                        <div class="mission-form-row">
                                            <label for="missionSpeed">Waypoint Speed (m/s)</label>
                                            <input class="mission-input" id="missionSpeed" type="number" x-model.number="waypointSpeed" min="0.5" max="15" step="0.1" required>
                                        </div>
                                        <div class="mission-form-row">
                                            <label for="missionOverlap">Overlap (%)</label>
                                            <input class="mission-input" id="missionOverlap" type="number" x-model.number="overlap" min="0" max="99" step="0.1" required>
                                        </div>
                                        <div class="mission-form-row">
                                            <label for="missionGimbal">Gimbal Angle (&deg;)</label>
                                            <input class="mission-input" id="missionGimbal" type="number" x-model.number="gimbalAngle" min="-90" max="30" step="1" required>
                                        </div>
                                    </div>

                                    @if ($showAdvancedMissionSettings)
                                        <details class="mission-advanced">
                                            <summary class="mission-advanced-header">
                                                <span class="mission-advanced-title"><i class="fa-solid fa-sliders"></i> Advanced</span>
                                                <button type="button" class="mission-reset-btn" @click.stop.prevent="resetAdvancedSettings" title="Reset advanced settings to their defaults">
                                                    <i class="fa-solid fa-rotate-left"></i> Reset to default
                                                </button>
                                            </summary>
                                            <div class="mission-grid">
                                                <div class="mission-form-row">
                                                    <label for="missionFinishAction">On Completion</label>
                                                    <select class="mission-select" id="missionFinishAction" x-model="finishAction">
                                                        <option value="goHome">Return to home</option>
                                                        <option value="autoLand">Auto land</option>
                                                        <option value="gotoFirstWaypoint">Go to first waypoint</option>
                                                        <option value="noAction">No action</option>
                                                    </select>
                                                </div>
                                                <div class="mission-form-row">
                                                    <label for="missionRcLostAction">If Signal Lost</label>
                                                    <select class="mission-select" id="missionRcLostAction" x-model="executeRcLostAction">
                                                        <option value="goHome">Return to home</option>
                                                        <option value="landing">Land immediately</option>
                                                        <option value="hover">Hover in place</option>
                                                    </select>
                                                </div>
                                                <div class="mission-form-row">
                                                    <label for="missionTransitionalSpeed">Transit Speed (m/s)</label>
                                                    <input class="mission-input" id="missionTransitionalSpeed" type="number" x-model.number="transitionalSpeed" min="0.5" max="15" step="0.1" required>
                                                </div>
                                            </div>
                                        </details>
                                    @endif

                                    <div class="mission-form-actions">
                                        <button class="mission-generate" type="submit" :disabled="generating">
                                            <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-map-location-dot'"></i>
                                            <span x-text="generating ? 'Generating...' : 'Create Waypoint'"></span>
                                        </button>
                                        <a
                                            class="mission-generate mission-download"
                                            :href="downloadUrl"
                                            x-show="downloadUrl"
                                            x-cloak
                                            data-no-page-transition
                                        >
                                            <i class="fa-solid fa-download"></i>
                                            <span>Download KMZ</span>
                                        </a>
                                        <div class="mission-status" :class="{ 'error': statusType === 'error' }" x-text="statusMessage"></div>
                                    </div>
                                </form>
                            </section>
                        @endif

                        @if ($isOrthomosaicRoute)
                            <section class="dock-section" id="orthomosaicPanel">
                                <div class="dock-section-title">
                                    <i class="fa-solid fa-layer-group"></i> <span>Orthomosaic</span>
                                    <button type="button" class="dock-section-toggle" aria-expanded="true" title="Collapse Orthomosaic">
                                        <i class="fa-solid fa-angle-down"></i>
                                    </button>
                                </div>
                                <div class="dock-section-body">
                                    <input type="file" id="orthomosaicFileInput" accept=".tif,.tiff,image/tiff" hidden>

                                    <div id="orthomosaicUploadState">
                                        <p class="mission-lock-notice">
                                            <i class="fa-solid fa-circle-info"></i>
                                            Upload a GeoTIFF (.tif) orthomosaic to preview it on the map. Large files may take a moment to render.
                                        </p>
                                        <button type="button" class="mission-generate" id="orthomosaicUploadBtn">
                                            <i class="fa-solid fa-cloud-arrow-up" id="orthomosaicUploadIcon"></i>
                                            <span id="orthomosaicUploadLabel">Upload Orthomosaic</span>
                                        </button>
                                    </div>

                                    <div id="orthomosaicManageState" hidden>
                                        <p class="mission-lock-notice">
                                            <i class="fa-solid fa-circle-check"></i>
                                            <span id="orthomosaicManageInfo">Orthomosaic uploaded.</span>
                                        </p>
                                        <button type="button" class="mission-generate" id="orthomosaicAnalyzeBtn">
                                            <i class="fa-solid fa-wand-magic-sparkles" id="orthomosaicAnalyzeIcon"></i>
                                            <span id="orthomosaicAnalyzeLabel">Analyze Orthomosaic</span>
                                        </button>
                                        <button type="button" class="mission-generate orthomosaic-remove-btn" id="orthomosaicCancelBtn" hidden>
                                            <i class="fa-solid fa-xmark"></i>
                                            <span>Cancel Analysis</span>
                                        </button>
                                        <button type="button" class="mission-generate orthomosaic-remove-btn" id="orthomosaicRemoveBtn">
                                            <i class="fa-solid fa-trash"></i>
                                            <span>Remove Orthomosaic</span>
                                        </button>
                                    </div>

                                    {{-- Every new orthomosaic gets a fresh, independent clustering fit
                                         (no learning carried over between uploads), so its clusters always
                                         need a human to name them before scoring means anything. --}}
                                    <div id="orthomosaicLabelingState" hidden>
                                        <p class="mission-lock-notice">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <span id="orthomosaicQualityInfo"></span> Review each cluster's sample tiles below and assign what it actually shows.
                                        </p>
                                        <div id="clusterLabelList" class="cluster-label-list"></div>
                                        <button type="button" class="mission-generate" id="submitLabelsBtn">
                                            <i class="fa-solid fa-check"></i>
                                            <span>Submit Labels</span>
                                        </button>
                                    </div>

                                    <div id="orthomosaicResultsState" hidden>
                                        <p class="mission-lock-notice">
                                            <i class="fa-solid fa-circle-check"></i>
                                            <span id="orthomosaicResultsInfo"></span>
                                        </p>
                                        <button type="button" class="mission-generate" id="editGridCellsBtn" aria-pressed="false">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                            <span id="editGridCellsLabel">Correct Grid Cells</span>
                                        </button>
                                        <p class="mission-lock-notice" id="editGridCellsHint" hidden>
                                            <i class="fa-solid fa-circle-info"></i>
                                            Click a cell on the map, then pick its correct label. Corrections are
                                            saved immediately and marked as human-reviewed.
                                        </p>
                                        {{-- Hidden while classification itself is being finalized -- not
                                             deleted, just deferred. Re-add `hidden` removal here once
                                             sampling point generation is back in scope. --}}
                                        <button type="button" class="mission-generate" id="generateSamplingPointsBtn" hidden>
                                            <i class="fa-solid fa-location-dot"></i>
                                            <span>Generate Sampling Points</span>
                                        </button>
                                        <div id="samplingPointsInfo" class="mission-lock-notice" hidden></div>
                                    </div>

                                    <div class="classification-progress-bar" id="orthomosaicProgressBar" hidden>
                                        <div class="classification-progress-bar-fill" id="orthomosaicProgressBarFill"></div>
                                    </div>
                                    <div class="mission-status" id="orthomosaicStatus"></div>
                                </div>
                            </section>
                        @endif
                    </div>
                </aside>
            @endif

            <div class="map-viewport">
                @if ($showMapStack)
                    <div id="workspace-map"></div>
                @endif

                @if ($isSoilReadingsRoute && $collectedSoilReadings->isNotEmpty())
                    <div class="report-dashboard-panel" id="soilReadingsTablePanel" hidden>
                        <div class="report-dashboard-inner">
                            <section class="soil-readings-card">
                                <div class="soil-readings-table-controls">
                                    <label>
                                        Rating
                                        <select id="soilReadingsFilterRating" class="mission-select">
                                            <option value="all">All</option>
                                            <option value="suitable">Suitable</option>
                                            <option value="marginal">Marginal</option>
                                            <option value="unsuitable">Unsuitable</option>
                                        </select>
                                    </label>
                                    <label>
                                        Sort by
                                        <select id="soilReadingsSort" class="mission-select">
                                            <option value="newest">Newest first</option>
                                            <option value="oldest">Oldest first</option>
                                            <option value="point">Point #</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="soil-readings-table-wrap">
                                    <table class="soil-readings-table" id="soilReadingsTable">
                                        <thead>
                                            <tr>
                                                <th>Point #</th>
                                                <th>Coordinates</th>
                                                <th>Species</th>
                                                <th>Rating</th>
                                                <th>pH</th>
                                                <th>Salinity (ppt)</th>
                                                <th>Moisture</th>
                                                <th>N</th>
                                                <th>P</th>
                                                <th>K</th>
                                                <th>Collected By</th>
                                                <th>Collected At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($collectedSoilReadings as $reading)
                                                @php
                                                    $phTint = \App\Services\SpeciesReference::classify('ph', $reading->species, $reading->ph_avg);
                                                    $salinityTint = \App\Services\SpeciesReference::classify('salinity_ppt', $reading->species, $reading->salinity_avg);
                                                @endphp
                                                <tr data-rating="{{ $reading->suitability_rating }}" data-collected-at="{{ $reading->collected_at?->timestamp }}" data-point-rank="{{ $reading->samplingPoint?->priority_rank }}">
                                                    <td>{{ $reading->samplingPoint?->priority_rank ?? '?' }}</td>
                                                    <td>{{ $reading->samplingPoint ? number_format($reading->samplingPoint->latitude, 5) . ', ' . number_format($reading->samplingPoint->longitude, 5) : '--' }}</td>
                                                    <td>{{ \App\Services\SpeciesReference::label($reading->species) ?? '--' }}</td>
                                                    <td>
                                                        @if ($reading->suitability_rating)
                                                            <span class="soil-reading-rating soil-reading-rating--{{ $reading->suitability_rating }}">{{ ucfirst($reading->suitability_rating) }}</span>
                                                        @else
                                                            --
                                                        @endif
                                                    </td>
                                                    <td class="{{ $phTint ? 'ref-' . $phTint : '' }}">{{ $reading->ph_avg !== null ? number_format($reading->ph_avg, 2) : '--' }}</td>
                                                    <td class="{{ $salinityTint ? 'ref-' . $salinityTint : '' }}">{{ $reading->salinity_avg !== null ? number_format($reading->salinity_avg, 2) : '--' }}</td>
                                                    <td>{{ $reading->moisture_avg !== null ? number_format($reading->moisture_avg, 1) . '%' : '--' }}</td>
                                                    <td>{{ $reading->nitrogen_avg !== null ? number_format($reading->nitrogen_avg, 1) : '--' }}</td>
                                                    <td>{{ $reading->phosphorus_avg !== null ? number_format($reading->phosphorus_avg, 1) : '--' }}</td>
                                                    <td>{{ $reading->potassium_avg !== null ? number_format($reading->potassium_avg, 1) : '--' }}</td>
                                                    <td>{{ $reading->collector?->name ?? 'Unknown' }}</td>
                                                    <td>{{ $reading->collected_at?->format('M d, Y g:i A') ?? '--' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </div>
                @endif

                @if ($isReportsRoute)
                    @php
                        $bareSoilPercent = ($reportsChartData['land_cover_breakdown'] ?? collect())->firstWhere('label', 'Bare Soil')['percent'] ?? 0;
                        $suitableCount = ($reportsChartData['suitability_breakdown'] ?? collect())->firstWhere('rating', 'suitable')['count'] ?? 0;
                        $suitablePercent = ($reportsChartData['total_readings'] ?? 0) > 0
                            ? round($suitableCount / $reportsChartData['total_readings'] * 100)
                            : 0;
                    @endphp
                    <div class="report-dashboard-panel">
                        <div class="report-dashboard-inner">
                            @if ($reportsChartData)
                                <div class="report-kpi-grid">
                                    <div class="project-stat-tile">
                                        <span class="project-stat-value">{{ $reportsChartData['total_cells'] }}</span>
                                        <span class="project-stat-label">Grid Cells Scored</span>
                                    </div>
                                    <div class="project-stat-tile">
                                        <span class="project-stat-value">{{ $bareSoilPercent }}%</span>
                                        <span class="project-stat-label">Bare Soil Coverage</span>
                                    </div>
                                    <div class="project-stat-tile">
                                        <span class="project-stat-value">{{ $reportsChartData['total_points'] }}</span>
                                        <span class="project-stat-label">Sampling Points</span>
                                    </div>
                                    <div class="project-stat-tile">
                                        <span class="project-stat-value">{{ $reportsChartData['total_readings'] }}</span>
                                        <span class="project-stat-label">Readings Collected</span>
                                    </div>
                                    <div class="project-stat-tile">
                                        <span class="project-stat-value">{{ $reportsChartData['total_readings'] > 0 ? $suitablePercent . '%' : '—' }}</span>
                                        <span class="project-stat-label">Rated Suitable</span>
                                    </div>
                                </div>
                            @endif

                            <div class="report-chart-grid">
                                <section class="soil-readings-card report-chart-card report-chart-card--wide">
                                    <h3>Survey Map</h3>
                                    @if ($existingOrthomosaicConfig && $existingOrthomosaicConfig['preview_url'])
                                        <div class="report-mini-map-wrap">
                                            <div id="reportMiniMap"></div>
                                        </div>
                                        <div class="report-mini-map-legend" id="reportMiniMapLegend" hidden></div>
                                    @else
                                        <p class="soil-readings-empty-hint">No orthomosaic preview available yet.</p>
                                    @endif
                                </section>

                                <section class="soil-readings-card report-chart-card">
                                    <h3>Land Cover Breakdown</h3>
                                    @if ($reportsChartData && $reportsChartData['total_cells'] > 0)
                                        <div class="report-chart-canvas-wrap">
                                            <canvas id="reportLandCoverChart"></canvas>
                                        </div>
                                    @else
                                        <p class="soil-readings-empty-hint">No classification results yet — run AI classification on the Orthomosaic step first.</p>
                                    @endif
                                </section>

                                <section class="soil-readings-card report-chart-card">
                                    <h3>Suitability Rating Breakdown</h3>
                                    @if (($reportsChartData['total_readings'] ?? 0) > 0)
                                        <div class="report-chart-canvas-wrap">
                                            <canvas id="reportSuitabilityChart"></canvas>
                                        </div>
                                    @else
                                        <p class="soil-readings-empty-hint">No soil readings synced from the field yet — charts will appear here once field data starts coming in through the mobile app.</p>
                                    @endif
                                </section>

                                <section class="soil-readings-card report-chart-card report-chart-card--wide">
                                    <h3>Soil Parameter Ranges</h3>
                                    @if (($reportsChartData['total_readings'] ?? 0) > 0)
                                        <div id="reportParameterRanges" class="report-parameter-ranges"></div>
                                    @else
                                        <p class="soil-readings-empty-hint">No measurements recorded yet.</p>
                                    @endif
                                </section>

                                <section class="soil-readings-card report-chart-card report-chart-card--wide">
                                    <h3>Readings Collected Over Time</h3>
                                    @if (($reportsChartData['total_readings'] ?? 0) > 0)
                                        <div class="report-chart-canvas-wrap">
                                            <canvas id="reportTimelineChart"></canvas>
                                        </div>
                                    @else
                                        <p class="soil-readings-empty-hint">No collection dates recorded yet.</p>
                                    @endif
                                </section>

                                <section class="soil-readings-card report-chart-card">
                                    <h3>Survey Details</h3>
                                    @if ($reportsChartData && ($reportsChartData['aoi'] || $reportsChartData['flight']))
                                        <div class="report-detail-list">
                                            @if ($reportsChartData['aoi'])
                                                <div class="report-detail-row"><span>Area of Interest</span><strong>{{ $reportsChartData['aoi']['name'] ?: 'Unnamed' }}</strong></div>
                                                <div class="report-detail-row"><span>AOI Size</span><strong>{{ $reportsChartData['aoi']['area_ha'] !== null ? number_format($reportsChartData['aoi']['area_ha'], 2) . ' ha' : '--' }}</strong></div>
                                                <div class="report-detail-row"><span>Center Coordinates</span><strong>{{ $reportsChartData['aoi']['center_latitude'] !== null ? number_format($reportsChartData['aoi']['center_latitude'], 5) . ', ' . number_format($reportsChartData['aoi']['center_longitude'], 5) : '--' }}</strong></div>
                                            @endif
                                            @if ($reportsChartData['flight'])
                                                <div class="report-detail-row"><span>Flight Altitude</span><strong>{{ $reportsChartData['flight']['altitude_m'] !== null ? number_format($reportsChartData['flight']['altitude_m'], 0) . ' m' : '--' }}</strong></div>
                                                <div class="report-detail-row"><span>Image Overlap</span><strong>{{ $reportsChartData['flight']['overlap_pct'] !== null ? number_format($reportsChartData['flight']['overlap_pct'], 0) . '%' : '--' }}</strong></div>
                                                <div class="report-detail-row"><span>Flight Speed</span><strong>{{ $reportsChartData['flight']['speed_mps'] !== null ? number_format($reportsChartData['flight']['speed_mps'], 1) . ' m/s' : '--' }}</strong></div>
                                                <div class="report-detail-row"><span>Waypoints</span><strong>{{ $reportsChartData['flight']['waypoint_count'] ?? '--' }}</strong></div>
                                                <div class="report-detail-row"><span>Line Spacing</span><strong>{{ $reportsChartData['flight']['line_spacing_meters'] !== null ? number_format($reportsChartData['flight']['line_spacing_meters'], 1) . ' m' : '--' }}</strong></div>
                                                <div class="report-detail-row"><span>Total Flight Distance</span><strong>{{ $reportsChartData['flight']['total_distance_meters'] !== null ? number_format($reportsChartData['flight']['total_distance_meters'], 0) . ' m' : '--' }}</strong></div>
                                            @endif
                                        </div>
                                    @else
                                        <p class="soil-readings-empty-hint">No area/flight details available yet.</p>
                                    @endif
                                </section>

                                <section class="soil-readings-card report-chart-card">
                                    <h3>Readings by Species</h3>
                                    @if ($reportsChartData && $reportsChartData['species_breakdown']->isNotEmpty())
                                        <table class="soil-readings-table">
                                            <thead>
                                                <tr>
                                                    <th>Species</th>
                                                    <th>Readings</th>
                                                    <th>Suitable</th>
                                                    <th>Marginal</th>
                                                    <th>Unsuitable</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($reportsChartData['species_breakdown'] as $row)
                                                    <tr>
                                                        <td>{{ $row['label'] ?? $row['species'] }}</td>
                                                        <td>{{ $row['count'] }}</td>
                                                        <td>{{ $row['suitable'] }}</td>
                                                        <td>{{ $row['marginal'] }}</td>
                                                        <td>{{ $row['unsuitable'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <p class="soil-readings-empty-hint">No readings with a recorded species yet.</p>
                                    @endif
                                </section>
                            </div>

                            <div class="report-actions-row">
                            <section class="soil-readings-card">
                                <h2><i class="fa-solid fa-file-lines"></i> Generate a Report</h2>
                                @if (!$canGenerateReport)
                                    <p class="soil-readings-empty-hint">
                                        Classification must finish on the Orthomosaic step before a report can be generated.
                                    </p>
                                @else
                                    <p class="soil-readings-empty-hint" style="margin-bottom: 14px;">
                                        Builds a PDF covering the suitability overlay, land cover breakdown, and every
                                        sampling point with whatever soil reading has been recorded for it so far.
                                    </p>
                                    <p class="soil-readings-status" id="reportGenerateStatus" hidden></p>
                                    <button type="button" class="mission-generate" id="reportGenerateBtn">
                                        <i class="fa-solid fa-file-circle-plus"></i>
                                        <span>Generate Full Survey Report</span>
                                    </button>
                                @endif
                            </section>

                            <section class="soil-readings-card">
                                <h2><i class="fa-solid fa-list"></i> Generated Reports ({{ $existingReports->count() }})</h2>
                                @if ($existingReports->isEmpty())
                                    <p class="soil-readings-empty-hint">No reports have been generated for this project yet.</p>
                                @else
                                    <div class="soil-readings-list" id="reportsList">
                                        @foreach ($existingReports as $report)
                                            <div class="soil-reading-row">
                                                <div class="soil-reading-row-main">
                                                    <strong>{{ $report->title }}</strong>
                                                </div>
                                                <div class="soil-reading-row-footer">
                                                    Generated {{ $report->generated_at?->format('M d, Y g:i A') }}
                                                    &middot; {{ $report->download_count }} download{{ $report->download_count === 1 ? '' : 's' }}
                                                    &middot; <a href="{{ route('projects.reports.download', [$project, $report]) }}">Download PDF</a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($isAoiRoute)
                    <section class="floating-panel glass-panel aoi-instruction" id="aoiInstruction" {{ $activeAoi ? 'hidden' : '' }}>
                        <div class="aoi-instruction-body">
                            <i class="fa-solid fa-crosshairs"></i>
                            <p id="aoiInstructionText">Click on the map to place each corner of your Area of Interest, then click the first point again (or double-click) to close the shape. Max 4 hectares (200 m x 200 m), 200 m longest dimension. Press Esc anytime to cancel.</p>
                            <button class="aoi-dismiss" type="button" id="dismissAoiInstruction" title="Dismiss instruction">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </section>
                @endif

                {{-- Available on every map-bearing route, not just AOI --
                     e.g. jumping to an orthomosaic's real coordinates while
                     reviewing it on the Orthomosaic page, which is outside
                     the Davao Occidental restriction that the place-name
                     mode below still respects. Coordinates mode is a
                     separate, explicit toggle rather than trying to guess
                     from the input text whether "6.06, -77.36" is a place
                     query or a coordinate pair. --}}
                @if ($showMapStack)
                    <div class="floating-panel glass-panel map-search-bar" id="missionMapSearch">
                        <div class="map-search-mode-toggle" role="group" aria-label="Search mode">
                            <button type="button" class="map-search-mode-btn active" id="mapSearchModePlace" data-mode="place">Place</button>
                            <button type="button" class="map-search-mode-btn" id="mapSearchModeCoords" data-mode="coordinates">Coordinates</button>
                        </div>
                        <form class="map-search-form" id="missionMapSearchForm" data-no-page-transition autocomplete="off">
                            <i class="fa-solid fa-magnifying-glass map-search-icon"></i>
                            <input type="search" class="map-search-input" id="missionMapSearchInput" placeholder="Search a location..." autocomplete="off">
                        </form>
                        <p class="map-search-hint" id="mapSearchCoordsHint" hidden>Format: latitude, longitude (e.g. 6.0596, -77.3629)</p>
                        <div class="aoi-search-suggestions map-search-suggestions" id="missionMapSearchSuggestions" hidden></div>
                    </div>
                @endif

                {{-- Gated on $isAoiRoute, not $showMissionPanel -- same
                     reason as the Alpine root above: this needs to exist in
                     the DOM (just hidden) from initial page load so it can
                     be revealed in place once a mission is generated,
                     without a reload. renderMissionPath() unhides it. --}}
                @if ($isAoiRoute)
                    <div class="floating-panel glass-panel mission-path-legend" id="missionPathLegend" hidden>
                        <div class="mission-legend-title">Flight Path</div>
                        <div class="mission-legend-item"><span class="mission-legend-swatch start"><i class="fa-solid fa-plane-departure"></i></span> Start</div>
                        <div class="mission-legend-item"><span class="mission-legend-swatch waypoint"></span> Waypoint</div>
                        <div class="mission-legend-item"><span class="mission-legend-swatch end"><i class="fa-solid fa-flag-checkered"></i></span> End</div>
                        <div class="mission-legend-item"><span class="mission-legend-line"></span> Flight path</div>
                        <div class="mission-legend-item"><span class="mission-legend-arrow"><i class="fa-solid fa-caret-up"></i></span> Direction of travel</div>
                    </div>
                @endif

                {{-- Present from page load (hidden), same reasoning as the
                     flight path legend above -- populated/shown by
                     renderSuitabilityLegend() in map-workspace.js from
                     whatever the active classification run's own legend
                     data says (ClassificationController::status()'s
                     'legend' field / existingClassification.legend), not
                     hardcoded here, since the color/label set is decided
                     by the classification pipeline. --}}
                @if ($isOrthomosaicRoute || $isSamplingPointsRoute)
                    <div class="floating-panel glass-panel suitability-legend" id="suitabilityLegend" hidden>
                        <div class="mission-legend-title">Suitability</div>
                        <div id="suitabilityLegendItems"></div>
                    </div>
                @endif

                @if ($aoiTotal < 1)
                    <div class="floating-panel glass-panel aoi-toast">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>No AOI defined yet - open the Area of Interest tab to get started.</span>
                    </div>
                @endif

                @if ($isAoiRoute)
                    <div class="floating-panel glass-panel aoi-map-toast" id="aoiMapToast" hidden></div>

                    <div class="aoi-redraw-modal-backdrop" id="aoiRedrawModalBackdrop"></div>
                    <div class="aoi-redraw-modal-card" id="aoiRedrawModalCard" role="alertdialog" aria-modal="true" aria-labelledby="aoiRedrawModalTitle">
                        <div class="aoi-redraw-modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <h3 id="aoiRedrawModalTitle">Redraw AOI?</h3>
                        <p>This clears the current AOI and any drone mission generated for it — you'll need to regenerate the flight plan afterward. Continue?</p>
                        <div class="aoi-redraw-modal-actions">
                            <button type="button" class="aoi-redraw-modal-cancel" id="aoiRedrawModalCancel">Cancel</button>
                            <button type="button" class="aoi-redraw-modal-confirm" id="aoiRedrawModalConfirm">Redraw</button>
                        </div>
                    </div>
                @endif

                {{-- Reuses the AOI redraw modal's styling (same classes) --
                     an in-page overlay reads as part of the app and is far
                     more visible/on-brand than a native browser confirm()
                     popup, which is easy to miss or dismiss by reflex. --}}
                @if ($isOrthomosaicRoute)
                    <div class="aoi-redraw-modal-backdrop" id="orthomosaicRemoveModalBackdrop"></div>
                    <div class="aoi-redraw-modal-card" id="orthomosaicRemoveModalCard" role="alertdialog" aria-modal="true" aria-labelledby="orthomosaicRemoveModalTitle">
                        <div class="aoi-redraw-modal-icon"><i class="fa-solid fa-trash"></i></div>
                        <h3 id="orthomosaicRemoveModalTitle">Remove orthomosaic?</h3>
                        <p>This cannot be undone.</p>
                        <div class="aoi-redraw-modal-actions">
                            <button type="button" class="aoi-redraw-modal-cancel" id="orthomosaicRemoveModalCancel">Cancel</button>
                            <button type="button" class="aoi-redraw-modal-confirm" id="orthomosaicRemoveModalConfirm">Remove</button>
                        </div>
                    </div>
                @endif

                <div class="gis-statusbar">
                    <div class="statusbar-item">
                        <i class="fa-solid fa-location-crosshairs"></i>
                        <span id="compassLat">--</span>
                        <span id="compassLng">--</span>
                    </div>
                    <div class="statusbar-sep"></div>
                    <div class="statusbar-item" id="compassElevation">Elevation --</div>
                    <div class="statusbar-sep"></div>
                    <div class="statusbar-item" id="compassEyeAltitude">Eye altitude --</div>
                </div>
            </div>

            @unless ($isReportsRoute)
            <aside class="dock dock-right" id="propertiesDock">
                <div class="dock-header">
                    <span class="dock-header-icon"><i class="fa-solid fa-circle-info"></i></span>
                    <span class="dock-header-text">Properties</span>
                    <button class="dock-collapse-btn" type="button" data-collapse-target="propertiesDock" title="Toggle properties">
                        <i class="fa-solid fa-angle-right"></i>
                    </button>
                </div>
                <div class="dock-scroll">
                    <section class="dock-section" id="projectInfoPanel">
                        <h1>{{ $projectName }}</h1>
                        <div class="project-meta">
                            <span class="status-badge {{ $projectStatus }}">
                                <span class="status-dot"></span>
                                {{ ucfirst($projectStatus) }}
                            </span>
                            <span><i class="fa-regular fa-calendar"></i> {{ $createdDate }}</span>
                        </div>
                        @if ($showFullProjectInfo)
                            <div class="project-stat-grid">
                                <div class="project-stat-tile">
                                    <span class="project-stat-value">{{ $aoiTotal > 0 ? number_format(($activeAoi->area_sqm ?? 0) / 10000, 1) . ' ha' : '—' }}</span>
                                    <span class="project-stat-label">AOI Area</span>
                                </div>
                                <div class="project-stat-tile">
                                    <span class="project-stat-value">{{ $missionTotal }}</span>
                                    <span class="project-stat-label">Drone Mission{{ $missionTotal === 1 ? '' : 's' }}</span>
                                </div>
                                <div class="project-stat-tile">
                                    <span class="project-stat-value">{{ $orthomosaicTotal }}</span>
                                    <span class="project-stat-label">Orthomosaic{{ $orthomosaicTotal === 1 ? '' : 's' }}</span>
                                </div>
                                <div class="project-stat-tile">
                                    <span class="project-stat-value">{{ $samplingTotal }}</span>
                                    <span class="project-stat-label">Sampling Points</span>
                                </div>
                            </div>
                            <div class="project-suitability">
                                @if(!is_null($suitabilityRate ?? null))
                                    <span class="status-badge">
                                        <span class="status-dot"></span>
                                        {{ $suitabilityRate }}% suitable
                                    </span>
                                    <p>Of the soil readings rated so far across this project's sampling points, {{ $suitabilityRate }}% came back suitable for planting. Classification already analyzes every uploaded orthomosaic for land cover (bare soil, vegetation, open water) and scores each cell for sampling priority automatically.</p>
                                @else
                                    <span class="status-badge pending">
                                        <span class="status-dot"></span>
                                        No ratings yet
                                    </span>
                                    <p>Classification already analyzes every uploaded orthomosaic for land cover and scores each cell for sampling priority automatically. This card fills in with real numbers once field-collected soil readings start coming back rated.</p>
                                @endif
                            </div>
                        @endif
                    </section>

                    @if ($isAoiRoute)
                        <section class="dock-section" id="aoiInfoPanel" hidden>
                            <div class="dock-section-title"><i class="fa-solid fa-draw-polygon"></i> <span id="aoiPanelTitle">AOI Preview</span></div>
                            <div class="aoi-info-grid">
                                <span>
                                    <span class="aoi-metric-label">Area</span>
                                    <span class="aoi-metric-value" id="aoiAreaValue">Pending</span>
                                </span>
                                <span>
                                    <span class="aoi-metric-label">Center Coordinates</span>
                                    <span class="aoi-metric-value" id="aoiCenterValue">Pending</span>
                                </span>
                                <span>
                                    <span class="aoi-metric-label">Longest Dimension</span>
                                    <span class="aoi-metric-value" id="aoiDimensionValue">Pending</span>
                                </span>
                            </div>
                            <p class="aoi-metric-hint">Area/dimension shown here are estimates for preview only — the server re-checks the exact 4 hectare / 200 m caps and self-intersection when you confirm.</p>
                            <div class="aoi-panel-actions" id="aoiPanelActions"></div>
                        </section>
                    @endif

                    @if ($orthomosaicDetails)
                        <section class="dock-section" id="orthomosaicInfoPanel">
                            <div class="dock-section-title"><i class="fa-solid fa-layer-group"></i> <span>Orthomosaic Details</span></div>
                            <div class="aoi-info-grid">
                                @if ($orthomosaicDetails['footprint_area_ha'] !== null)
                                    <span>
                                        <span class="aoi-metric-label">Footprint Area</span>
                                        <span class="aoi-metric-value">{{ number_format($orthomosaicDetails['footprint_area_ha'], 2) }} ha</span>
                                    </span>
                                @endif
                                @if ($orthomosaicDetails['bounds'])
                                    <span>
                                        <span class="aoi-metric-label">Bounds (lat, lng)</span>
                                        <span class="aoi-metric-value">{{ $orthomosaicDetails['bounds'] }}</span>
                                    </span>
                                @endif
                                @if ($orthomosaicDetails['resolution_cm'])
                                    <span>
                                        <span class="aoi-metric-label">Ground Resolution</span>
                                        <span class="aoi-metric-value">{{ $orthomosaicDetails['resolution_cm'] }} cm/px</span>
                                    </span>
                                @endif
                                @if ($orthomosaicDetails['coordinate_system'])
                                    <span>
                                        <span class="aoi-metric-label">Coordinate System</span>
                                        <span class="aoi-metric-value">{{ $orthomosaicDetails['coordinate_system'] }}</span>
                                    </span>
                                @endif
                                @if ($orthomosaicDetails['file_format'])
                                    <span>
                                        <span class="aoi-metric-label">File Format</span>
                                        <span class="aoi-metric-value">{{ strtoupper($orthomosaicDetails['file_format']) }}</span>
                                    </span>
                                @endif
                                @if ($orthomosaicDetails['file_size'])
                                    <span>
                                        <span class="aoi-metric-label">File Size</span>
                                        <span class="aoi-metric-value">{{ $orthomosaicDetails['file_size'] }}</span>
                                    </span>
                                @endif
                                @if ($orthomosaicDetails['captured_at'])
                                    <span>
                                        <span class="aoi-metric-label">Captured</span>
                                        <span class="aoi-metric-value">{{ $orthomosaicDetails['captured_at'] }}</span>
                                    </span>
                                @endif
                                <span>
                                    <span class="aoi-metric-label">Uploaded</span>
                                    <span class="aoi-metric-value">{{ $orthomosaicDetails['uploaded_at'] }}{{ $orthomosaicDetails['uploaded_by'] ? ' by ' . $orthomosaicDetails['uploaded_by'] : '' }}</span>
                                </span>
                            </div>
                        </section>
                    @endif

                    {{-- Gated on $isAoiRoute, not $showMissionPanel -- same
                         reason as the Alpine root and the legend above. --}}
                    @if ($isAoiRoute)
                        <section class="dock-section mission-summary-section" id="missionSummarySection" x-cloak hidden>
                            <div class="dock-section-title"><i class="fa-solid fa-route"></i> <span>Mission Summary</span></div>
                            <div class="mission-summary-grid">
                                <span class="mission-summary-item">
                                    <span class="mission-summary-label">Waypoints</span>
                                    <span class="mission-summary-value" x-text="summary.waypointCount"></span>
                                </span>
                                <span class="mission-summary-item">
                                    <span class="mission-summary-label">Distance</span>
                                    <span class="mission-summary-value" x-text="summary.estimatedDistance"></span>
                                </span>
                                <span class="mission-summary-item">
                                    <span class="mission-summary-label">Flight Time</span>
                                    <span class="mission-summary-value" x-text="summary.estimatedFlightTime"></span>
                                </span>
                            </div>
                        </section>
                    @endif

                </div>
            </aside>
            @endunless

            <div class="legacy-content" aria-hidden="true">
                @yield('content')
                @include('partials.app-footer')
                <span>© 2026 Project M.A.N.G.R.O.V.E</span>
            </div>
        </main>
    </div>

    @if ($showMapStack || $isReportsRoute || $isComparisonsRoute)
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    @endif
    @if ($showMapStack)
        <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
        <script src="https://unpkg.com/@turf/turf@7/turf.min.js"></script>
        <script src="https://unpkg.com/georaster@1.6.0/dist/georaster.browser.bundle.min.js"></script>
        <script src="https://unpkg.com/georaster-layer-for-leaflet@3.10.0/dist/georaster-layer-for-leaflet.min.js"></script>
    @endif

    <script>
        window.workspaceConfig = {
            currentRoute: @json($currentRoute),
            isAoiRoute: @json($isAoiRoute),
            isMissionRoute: @json($showMissionPanel),
            isOrthomosaicRoute: @json($isOrthomosaicRoute),
            restrictMapToDavaoOccidental: @json($restrictMapToDavaoOccidental),
            lockMapToAoi: @json($lockMapToAoi),
            csrfToken: @json(csrf_token()),
            confirmAoiUrl: @json(isset($project) && !empty($project->id) ? route('projects.aoi.confirm', $project) : null),
            clearAoiUrl: @json(isset($project) && !empty($project->id) ? route('projects.aoi.clear', $project) : null),
            generateMissionUrl: @json(url('/api/missions/generate')),
            projectId: @json($project->id ?? null),
            savedAoi: @json($savedAoiConfig),
            savedMission: @json($savedMissionConfig),
            aoiCanBeRepositioned: @json(! $activeAoiHasCompletedMission),
            droneModels: @json($droneModels),
            orthomosaicUploadUrl: @json(isset($project) && !empty($project->id) ? route('projects.orthomosaic.upload', $project) : null),
            reportsGenerateUrl: @json(isset($project) && !empty($project->id) ? route('projects.reports.generate', $project) : null),
            existingOrthomosaic: @json($existingOrthomosaicConfig),
            existingClassification: @json($existingClassificationConfig),
            fieldTechnicians: @json($fieldTechnicians->values()),
            assignPointUrlTemplate: @json(isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/sampling-points/__POINT_ID__/assign') : null),
            assignAllPointsUrl: @json(isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/sampling-points/assign-all') : null),
            reportsChartData: @json($reportsChartData),
        };
    </script>

    <script>
        (function () {
            var toggle = document.getElementById('userMenuToggle');
            var dropdown = document.getElementById('userMenuDropdown');
            if (!toggle || !dropdown) return;

            function closeMenu() {
                dropdown.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }

            function openMenu() {
                dropdown.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
            }

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                if (dropdown.hidden) openMenu(); else closeMenu();
            });

            // Theme/sound toggles and the settings link all live inside the
            // dropdown, so a click on any of them would otherwise also
            // trigger the outside-click handler below on its way up --
            // stopping propagation here keeps the menu open through those
            // interactions except the ones that navigate/submit away
            // (settings link, logout) where staying open doesn't matter.
            dropdown.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            document.addEventListener('click', closeMenu);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closeMenu();
            });
        })();
    </script>

    <script>
        (function () {
            var legalModal = document.getElementById('legalModal');
            if (!legalModal) return;

            function showLegalTab(tabName) {
                legalModal.querySelectorAll('[data-legal-tab]').forEach(function (tab) {
                    tab.classList.toggle('is-active', tab.dataset.legalTab === tabName);
                });
                legalModal.querySelectorAll('[data-legal-panel]').forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.dataset.legalPanel === tabName);
                });
            }

            function closeLegalModal() {
                legalModal.classList.remove('is-open');
                legalModal.setAttribute('aria-hidden', 'true');
            }

            document.querySelectorAll('[data-legal-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    showLegalTab(button.dataset.legalOpen);
                    legalModal.classList.add('is-open');
                    legalModal.setAttribute('aria-hidden', 'false');
                });
            });

            legalModal.querySelectorAll('[data-legal-tab]').forEach(function (tab) {
                tab.addEventListener('click', function () { showLegalTab(tab.dataset.legalTab); });
            });

            legalModal.querySelector('[data-legal-close]').addEventListener('click', closeLegalModal);
            legalModal.addEventListener('click', function (event) {
                if (event.target === legalModal) closeLegalModal();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && legalModal.classList.contains('is-open')) closeLegalModal();
            });
        })();
    </script>

    <script>
        (function () {
            var btn = document.getElementById('reportGenerateBtn');
            if (!btn) return;

            var statusEl = document.getElementById('reportGenerateStatus');

            function setStatus(message, isError) {
                if (!statusEl) return;
                statusEl.textContent = message || '';
                statusEl.hidden = !message;
                statusEl.classList.toggle('is-error', !!isError);
                statusEl.classList.toggle('is-success', !!message && !isError);
            }

            btn.addEventListener('click', function () {
                btn.disabled = true;
                setStatus('Generating report -- this can take a few seconds...');

                fetch(window.workspaceConfig.reportsGenerateUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.workspaceConfig.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) throw new Error(data.message || 'Could not generate this report.');
                        return data;
                    });
                }).then(function (data) {
                    setStatus('Report generated -- downloading...');
                    // A GET to a Content-Disposition:attachment endpoint
                    // triggers a save-as download without navigating the
                    // browser away from this page, so it's safe to fire via
                    // location change rather than a fetch.
                    window.location.href = data.report.download_url;
                    window.setTimeout(function () { window.location.reload(); }, 1000);
                }).catch(function (error) {
                    setStatus(error.message || 'Could not generate this report.', true);
                    btn.disabled = false;
                });
            });
        })();
    </script>

    <script>
        // window.classificationRunActive is set by pollClassificationStatus()
        // in map-workspace.js whenever a classification run is actively
        // processing on this page. The job itself survives regardless of
        // whether this page stays open (it's a queued job on a dedicated
        // worker), so nothing is technically lost by navigating away -- but
        // it's easy to do by reflex (back button, an internal link) without
        // realizing a run is still going, and then wonder where it went.
        // beforeunload's browser-native confirmation covers every way of
        // leaving (back/forward, closing the tab, typing a new URL, internal
        // links) with one mechanism, not just a click handler on our own nav.
        window.addEventListener('beforeunload', function (event) {
            if (!window.classificationRunActive) return;
            event.preventDefault();
            event.returnValue = '';
        });
    </script>

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

</body>
</html>
