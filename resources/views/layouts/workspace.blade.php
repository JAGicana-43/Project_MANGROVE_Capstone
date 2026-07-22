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
    $currentRoute = $currentRoute ?? 'overview';
    $isAoiRoute = $currentRoute === 'aoi';
    $isMissionRoute = $currentRoute === 'drone-mission';
    $isOrthomosaicRoute = $currentRoute === 'orthomosaic';
    $showMapStack = $isAoiRoute || $isMissionRoute || $isOrthomosaicRoute || $currentRoute === 'overview';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Project Workspace') | Project MANGROVE</title>
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

    @if ($showMapStack)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
            crossorigin="" />

        <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    @endif

    @vite(['resources/css/workspace.css', 'resources/js/workspace/lite.js'])
    @if ($showMapStack)
        @vite(['resources/js/workspace/map.js'])
    @endif

    @include('partials.page-transition-styles')

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
        $activeOrthomosaic = $activeMission ? $activeMission->orthomosaics()->latest()->first() : null;
        $existingOrthomosaicConfig = $activeOrthomosaic ? [
            'id' => $activeOrthomosaic->id,
            'file_url' => route('projects.orthomosaic.file', ['project' => $project->id, 'orthomosaic' => $activeOrthomosaic->id]),
            'remove_url' => route('projects.orthomosaic.remove', ['project' => $project->id, 'orthomosaic' => $activeOrthomosaic->id]),
            'bounds_coordinates' => $activeOrthomosaic->bounds_coordinates,
            'uploaded_at' => $activeOrthomosaic->uploaded_at?->format('M d, Y g:i A'),
        ] : null;
        $droneModels = collect(config('drones', []))
            ->map(fn ($model, $key) => array_merge(['key' => $key], $model, ['name' => $model['label'] ?? $key]))
            ->values()
            ->all();

        $stepState = [
            'aoi' => $aoiTotal > 0 ? 'complete' : 'in-progress',
            'drone-mission' => $missionTotal > 0 ? 'complete' : ($aoiTotal > 0 ? 'in-progress' : 'pending'),
            // Orthomosaic upload and its AI classification are one workflow
            // step — classification runs automatically against the uploaded
            // orthomosaic rather than being a separate manual step.
            'orthomosaic' => $orthomosaicTotal > 0 ? 'complete' : ($missionTotal > 0 ? 'in-progress' : 'pending'),
            'sampling-points' => $samplingTotal > 0 ? 'complete' : ($orthomosaicTotal > 0 ? 'in-progress' : 'pending'),
            'soil-readings' => $soilTotal > 0 ? 'complete' : ($samplingTotal > 0 ? 'in-progress' : 'pending'),
            'reports' => $reportTotal > 0 ? 'complete' : ($soilTotal > 0 ? 'in-progress' : 'pending'),
        ];

        $workflowSteps = [
            ['short' => 'AOI', 'label' => 'AOI', 'route' => 'aoi', 'status' => $stepState['aoi']],
            ['short' => 'Mission', 'label' => 'Mission', 'route' => 'drone-mission', 'status' => $stepState['drone-mission']],
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
            ['label' => 'Area of Interest', 'icon' => 'fa-draw-polygon', 'route' => 'aoi', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/aoi') : '#', 'locked' => false],
            ['label' => 'Drone Mission', 'icon' => 'fa-helicopter', 'route' => 'drone-mission', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/drone-mission') : '#', 'locked' => $aoiTotal < 1],
            ['label' => 'Orthomosaic', 'icon' => 'fa-layer-group', 'route' => 'orthomosaic', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/orthomosaic') : '#', 'locked' => $missionTotal < 1],
            ['label' => 'Sampling Points', 'icon' => 'fa-map-pin', 'route' => 'sampling-points', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/sampling-points') : '#', 'locked' => $orthomosaicTotal < 1],
            ['label' => 'Soil Readings', 'icon' => 'fa-flask', 'route' => 'soil-readings', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/soil-readings') : '#', 'locked' => $samplingTotal < 1],
            ['label' => 'Reports', 'icon' => 'fa-file-lines', 'route' => 'reports', 'url' => isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/reports') : '#', 'locked' => $soilTotal < 1],
        ];

        foreach ($navItems as &$navItem) {
            $navItem['complete'] = ($stepState[$navItem['route']] ?? null) === 'complete';
        }
        unset($navItem);
    @endphp

    <nav class="gis-navbar">
        <div class="toolbar-side">
            <div class="toolbar-actions">
                <button class="icon-btn" id="navbarSidebarToggle" type="button" title="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <a href="{{ route('researcher.lobby') }}" class="icon-btn" title="Back to Lobby">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
            </div>
            <div class="brand-cluster">
                <span class="brand-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project MANGROVE logo"></span>
                <div class="brand-copy">
                    <span class="app-name">Project MANGROVE</span>
                    <span class="toolbar-divider">/</span>
                    <span class="project-name">{{ $projectName }}</span>
                </div>
            </div>
        </div>

        <div class="toolbar-side">
            <div class="mini-stepper" aria-label="Workflow step indicator">
                @foreach ($workflowSteps as $step)
                    @php
                        $stepUrl = isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/' . $step['route']) : '#';
                        $stepStatusLabel = match ($step['status']) {
                            'complete' => 'Complete',
                            'in-progress' => 'In progress',
                            default => 'Locked',
                        };
                        $stepTooltip = $step['label'] . ' - ' . $stepStatusLabel;
                    @endphp
                    @if ($step['status'] === 'complete')
                        <a class="mini-step {{ $step['status'] }}" href="{{ $stepUrl }}" title="{{ $stepTooltip }}" aria-label="{{ $stepTooltip }}" data-tooltip="{{ $stepTooltip }}" data-workflow-route="{{ $step['route'] }}"></a>
                    @else
                        <span class="mini-step {{ $step['status'] === 'pending' ? 'locked' : $step['status'] }}" title="{{ $stepTooltip }}" aria-label="{{ $stepTooltip }}" data-tooltip="{{ $stepTooltip }}" data-workflow-route="{{ $step['route'] }}" tabindex="0"></span>
                    @endif
                @endforeach
            </div>
            <div class="user-cluster">
                <button class="icon-btn" id="themeToggle" type="button" title="Toggle dark mode">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </button>
                <a href="{{ route('settings.account') }}" class="icon-btn" title="Account Settings">
                    <i class="fa-solid fa-gear"></i>
                </a>
                <div class="user-avatar">
                    @if ($workspaceUserAvatarUrl)
                        <img src="{{ $workspaceUserAvatarUrl }}" alt="{{ $workspaceUserName }}">
                    @else
                        {{ strtoupper(substr($workspaceUserName, 0, 1)) }}
                    @endif
                </div>
                <span class="user-name">{{ $workspaceUserName }}</span>
                <span class="role-badge">{{ $workspaceRoleName }}</span>
                <form method="POST" action="{{ url('/logout') }}" style="margin:0;" data-loading-label="Logging out...">
                    @csrf
                    <button type="submit" class="logout-btn" onclick="return openLogoutModal(event)">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span class="logout-text">Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main
        class="map-shell {{ $isAoiRoute && !$activeAoi ? 'aoi-placement-mode' : '' }}"
        id="mapShell"
        @if ($isMissionRoute) x-data="droneMissionPanel(window.workspaceConfig)" x-init="init()" @endif
    >
        @if ($showMapStack)
            <div id="workspace-map"></div>
        @endif

        <div class="compass-stack">
            <div class="floating-panel glass-panel compass-panel" id="compassPanel">
                <div class="compass-rose">
                    <span class="compass-label compass-n">N</span>
                    <span class="compass-label compass-s">S</span>
                    <span class="compass-label compass-w">W</span>
                    <span class="compass-label compass-e">E</span>
                    <i class="fa-solid fa-arrow-up compass-needle"></i>
                </div>
                <div class="compass-readout">
                    <div class="compass-coord" id="compassLat">--</div>
                    <div class="compass-coord" id="compassLng">--</div>
                    <div class="compass-divider"></div>
                    <div class="compass-elevation" id="compassElevation">Elevation --</div>
                    <div class="compass-eye-altitude" id="compassEyeAltitude">Eye altitude --</div>
                </div>
            </div>

            @if ($isMissionRoute)
                <div class="floating-panel glass-panel mission-path-legend" id="missionPathLegend" hidden>
                    <div class="mission-legend-title">Flight Path</div>
                    <div class="mission-legend-item"><span class="mission-legend-swatch start"><i class="fa-solid fa-plane-departure"></i></span> Start</div>
                    <div class="mission-legend-item"><span class="mission-legend-swatch waypoint"></span> Waypoint</div>
                    <div class="mission-legend-item"><span class="mission-legend-swatch end"><i class="fa-solid fa-flag-checkered"></i></span> End</div>
                    <div class="mission-legend-item"><span class="mission-legend-line"></span> Flight path</div>
                    <div class="mission-legend-item"><span class="mission-legend-arrow"><i class="fa-solid fa-caret-up"></i></span> Direction of travel</div>
                </div>
            @endif
        </div>
        @if ($isAoiRoute)
            <div class="aoi-pulse" id="aoiPulse" aria-hidden="true" {{ $activeAoi ? 'hidden' : '' }}></div>
            <div class="floating-panel glass-panel aoi-toolbar" id="aoiToolbar">
                <button type="button" class="aoi-toolbar-btn active" data-aoi-mode="polygon">
                    <i class="fa-solid fa-draw-polygon"></i> Polygon
                </button>
                <button type="button" class="aoi-toolbar-btn" data-aoi-mode="rectangle">
                    <i class="fa-solid fa-vector-square"></i> Rectangle
                </button>
                <button type="button" class="aoi-toolbar-btn" data-aoi-mode="corridor">
                    <i class="fa-solid fa-route"></i> Corridor
                </button>
                <div class="aoi-corridor-width" id="aoiCorridorWidth" hidden>
                    <label for="corridorWidthInput">Width (m)</label>
                    <input type="number" id="corridorWidthInput" min="10" max="150" step="5" value="50">
                </div>
            </div>
            <section class="floating-panel glass-panel aoi-instruction" id="aoiInstruction" {{ $activeAoi ? 'hidden' : '' }}>
                <div class="aoi-instruction-body">
                    <i class="fa-solid fa-crosshairs"></i>
                    <p id="aoiInstructionText">Click on the map to place each corner of your Area of Interest, then click the first point again (or double-click) to close the shape. Max 25 hectares (500 m x 500 m), 500 m longest dimension. Press Esc anytime to cancel.</p>
                    <button class="aoi-dismiss" type="button" id="dismissAoiInstruction" title="Dismiss instruction">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </section>
            <section class="floating-panel glass-panel aoi-search-panel" id="aoiSearchPanel" {{ $activeAoi ? 'hidden' : '' }}>
                <div class="aoi-search-mode-toggle" id="aoiSearchModeToggle">
                    <button type="button" class="aoi-search-mode-btn active" data-search-mode="name">
                        <i class="fa-solid fa-magnifying-glass"></i> Search
                    </button>
                    <button type="button" class="aoi-search-mode-btn" data-search-mode="coords">
                        <i class="fa-solid fa-location-dot"></i> Coordinates
                    </button>
                </div>
                <form class="aoi-search-form" id="aoiSearchForm" data-no-page-transition autocomplete="off">
                    <input class="aoi-search-field" id="aoiLocationSearch" type="search" placeholder="Search Davao Occidental (barangay, landmark...)" autocomplete="off">
                    <button class="aoi-search-button" type="submit" title="Search location">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </form>
                <div class="aoi-search-suggestions" id="aoiSearchSuggestions" hidden></div>
                <form class="aoi-coords-form" id="aoiCoordsForm" data-no-page-transition hidden>
                    <input class="aoi-search-field aoi-coords-field" id="aoiCoordsLat" type="number" step="any" placeholder="Latitude" autocomplete="off">
                    <input class="aoi-search-field aoi-coords-field" id="aoiCoordsLng" type="number" step="any" placeholder="Longitude" autocomplete="off">
                    <button class="aoi-search-button" type="submit" title="Go to coordinates">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
            </section>
        @endif

        <aside class="sidebar glass-panel" id="workspaceSidebar">
            <div class="sidebar-inner">
                <div class="sidebar-title">
                    <span class="sidebar-title-icon"><i class="fa-solid fa-map-location-dot"></i></span>
                    <span class="sidebar-title-text">Workspace Layers</span>
                </div>

                <ul class="sidebar-nav">
                    @foreach ($navItems as $item)
                        <li>
                            <a href="{{ $item['locked'] ? '#' : ($item['url'] ?? '#') }}" class="nav-link {{ $currentRoute === $item['route'] ? 'active' : '' }} {{ $item['locked'] ? 'locked' : '' }}" title="{{ $item['locked'] ? $item['label'] . ' is locked until the previous step is confirmed' : $item['label'] }}" aria-disabled="{{ $item['locked'] ? 'true' : 'false' }}" data-route="{{ $item['route'] }}" data-url="{{ $item['url'] ?? '#' }}">
                                <i class="fa-solid {{ $item['icon'] }}"></i>
                                <span class="nav-label">{{ $item['label'] }}</span>
                                @if ($item['locked'])
                                    <i class="fa-solid fa-lock lock-icon"></i>
                                @elseif ($item['complete'] ?? false)
                                    <i class="fa-solid fa-circle-check nav-check" aria-hidden="true" title="Completed"></i>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        <div class="panel-stack">
        <section class="floating-panel glass-panel project-card collapsible" id="projectInfoPanel">
            <div class="panel-header">
                <div class="panel-title"><i class="fa-solid fa-circle-info"></i> <span>Project Info</span></div>
                <button class="collapse-btn" type="button" data-collapse-target="projectInfoPanel" title="Collapse project info">
                    <i class="fa-solid fa-chevron-up"></i>
                </button>
            </div>
            <div class="panel-body">
                <h1>{{ $projectName }}</h1>
                <div class="project-meta">
                    <span class="status-badge {{ $projectStatus }}">
                        <span class="status-dot"></span>
                        {{ ucfirst($projectStatus) }}
                    </span>
                    <span><i class="fa-regular fa-calendar"></i> {{ $createdDate }}</span>
                </div>
            </div>
        </section>

        @if ($isMissionRoute)
            <section
                class="floating-panel glass-panel mission-panel collapsible"
                id="missionParametersPanel"
                x-cloak
            >
                <div class="panel-header">
                    <div class="panel-title"><i class="fa-solid fa-helicopter"></i> <span>Mission Parameters</span></div>
                    <button class="collapse-btn" type="button" data-collapse-target="missionParametersPanel" title="Collapse mission parameters">
                        <i class="fa-solid fa-chevron-up"></i>
                    </button>
                </div>
                <form class="panel-body" @submit.prevent="generateMission" data-no-page-transition>
                    <div class="mission-form-row">
                        <label for="droneModel">Drone Model</label>
                        <select class="mission-select" id="droneModel" x-model.number="droneModelIndex" required>
                            <template x-for="(model, index) in droneModels" :key="model.key">
                                <option :value="index" x-text="model.name"></option>
                            </template>
                        </select>
                    </div>

                    <p class="mission-lock-notice">
                        <i class="fa-solid fa-lock"></i>
                        Altitude, speed, gimbal angle, and overlap are fixed to match a validated real-world baseline mission so test missions stay repeatable and comparable. Only Drone Model is adjustable.
                    </p>

                    <div class="mission-grid">
                        <div class="mission-form-row">
                            <label for="missionAltitude">Altitude (m) <i class="fa-solid fa-lock mission-lock-icon"></i></label>
                            <input class="mission-input" id="missionAltitude" type="number" x-model.number="altitude" disabled>
                        </div>
                        <div class="mission-form-row">
                            <label for="missionSpeed">Waypoint Speed (m/s) <i class="fa-solid fa-lock mission-lock-icon"></i></label>
                            <input class="mission-input" id="missionSpeed" type="number" x-model.number="waypointSpeed" disabled>
                        </div>
                        <div class="mission-form-row">
                            <label for="missionOverlap">Overlap (%) <i class="fa-solid fa-lock mission-lock-icon"></i></label>
                            <input class="mission-input" id="missionOverlap" type="number" x-model.number="overlap" disabled>
                        </div>
                        <div class="mission-form-row">
                            <label for="missionGimbal">Gimbal Angle (&deg;) <i class="fa-solid fa-lock mission-lock-icon"></i></label>
                            <input class="mission-input" id="missionGimbal" type="number" x-model.number="gimbalAngle" disabled>
                        </div>
                    </div>

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
                </form>
            </section>
        @endif

        @if ($isOrthomosaicRoute)
            <section class="floating-panel glass-panel orthomosaic-panel collapsible" id="orthomosaicPanel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fa-solid fa-layer-group"></i> <span>Orthomosaic</span></div>
                    <button class="collapse-btn" type="button" data-collapse-target="orthomosaicPanel" title="Collapse orthomosaic">
                        <i class="fa-solid fa-chevron-up"></i>
                    </button>
                </div>
                <div class="panel-body">
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
                        <button type="button" class="mission-generate" id="orthomosaicAnalyzeBtn" disabled title="Analysis is coming in a future update">
                            <i class="fa-solid fa-lock"></i>
                            <span>Analyze Orthomosaic</span>
                        </button>
                        <button type="button" class="mission-generate orthomosaic-remove-btn" id="orthomosaicRemoveBtn">
                            <i class="fa-solid fa-trash"></i>
                            <span>Remove Orthomosaic</span>
                        </button>
                    </div>

                    <div class="mission-status" id="orthomosaicStatus"></div>
                </div>
            </section>
        @endif
        </div>

        @if ($isMissionRoute)
            <section
                class="floating-panel glass-panel mission-summary-panel"
                x-cloak
            >
                <div class="panel-title"><i class="fa-solid fa-route"></i> Mission Summary</div>
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

        <div class="floating-panel map-tools" aria-label="Map controls">
            <button class="map-tool-btn" id="locateMeBtn" type="button" title="Locate me">
                <i class="fa-solid fa-location-crosshairs"></i>
            </button>
            <button class="map-tool-btn" id="layerToggleBtn" type="button" title="Toggle layer">
                <i class="fa-solid fa-layer-group"></i>
            </button>
        </div>

        @if ($aoiTotal < 1)
            <div class="floating-panel glass-panel aoi-toast">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>No AOI defined yet - click 'Area of Interest' in the sidebar to get started.</span>
            </div>
        @endif

        @if ($isAoiRoute)
            <section class="floating-panel glass-panel aoi-info-panel" id="aoiInfoPanel" hidden>
                <div class="panel-header">
                    <div class="panel-title"><i class="fa-solid fa-draw-polygon"></i> <span id="aoiPanelTitle">AOI Preview</span></div>
                </div>
                <div class="panel-body">
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
                    <p class="aoi-metric-hint">Area/dimension shown here are estimates for preview only — the server re-checks the exact 25 hectare / 500 m caps and self-intersection when you confirm.</p>
                    <p class="aoi-cap-warning" id="aoiCapWarning" hidden></p>
                    <div class="aoi-panel-actions" id="aoiPanelActions">
                        <button class="aoi-action secondary" type="button" id="repositionAoiBtn">Redraw</button>
                        <button class="aoi-action primary" type="button" id="confirmAoiBtn">Confirm AOI</button>
                    </div>
                </div>
            </section>

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

        <div class="legacy-content" aria-hidden="true">
            @yield('content')
            @include('partials.site-footer')
            <span>© 2026 Project MANGROVE</span>
        </div>
    </main>

    @if ($showMapStack)
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>

        <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
        <script src="https://unpkg.com/@turf/turf@7/turf.min.js"></script>
        <script src="https://unpkg.com/georaster@1.6.0/dist/georaster.browser.bundle.min.js"></script>
        <script src="https://unpkg.com/georaster-layer-for-leaflet@3.10.0/dist/georaster-layer-for-leaflet.min.js"></script>
    @endif

    <script>
        window.workspaceConfig = {
            currentRoute: @json($currentRoute),
            isAoiRoute: @json($isAoiRoute),
            isMissionRoute: @json($isMissionRoute),
            isOrthomosaicRoute: @json($isOrthomosaicRoute),
            csrfToken: @json(csrf_token()),
            confirmAoiUrl: @json(isset($project) && !empty($project->id) ? route('projects.aoi.confirm', $project) : null),
            clearAoiUrl: @json(isset($project) && !empty($project->id) ? route('projects.aoi.clear', $project) : null),
            generateMissionUrl: @json(url('/api/missions/generate')),
            missionUrl: @json(isset($project) && !empty($project->id) ? url('/projects/' . $project->id . '/drone-mission') : '#'),
            projectId: @json($project->id ?? null),
            savedAoi: @json($savedAoiConfig),
            aoiCanBeRepositioned: @json(! $activeAoiHasCompletedMission),
            droneModels: @json($droneModels),
            orthomosaicUploadUrl: @json(isset($project) && !empty($project->id) ? route('projects.orthomosaic.upload', $project) : null),
            existingOrthomosaic: @json($existingOrthomosaicConfig),
        };
    </script>



    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

</body>
</html>
