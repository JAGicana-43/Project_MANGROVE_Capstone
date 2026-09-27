@extends('layouts.lobby')

@section('title', 'Project Lobby — Project M.A.N.G.R.O.V.E')

@section('navbar-subtitle', 'Researcher Lobby')

@section('page-content-class', 'page-content--full')

@section('navbar-center')
    @php
        $navGreetingHour = now('Asia/Manila')->hour;
        $navGreeting = match (true) {
            $navGreetingHour < 12 => 'Good morning',
            $navGreetingHour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    @endphp
    <div class="navbar-greeting">
        <span class="navbar-greeting-name">{{ $navGreeting }}, {{ $user->name ?? 'Researcher' }}</span>
    </div>

    <div class="navbar-spacer"></div>
@endsection

@section('content')

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

@if($user->needsWelcomeTour())
    @include('partials.welcome-carousel')
@endif

@include('partials.project-action-modals')

@if(session('error') || session('success'))
    {{-- Floating, not a normal block -- .atlas below is full-bleed/full-
         height, so a regular banner here would push it off screen. This is
         also what makes redirectIfArchived() (ProjectController) visible:
         it flashes 'error' and used to redirect here with nothing to show
         it. --}}
    <div class="atlas-flash {{ session('error') ? 'is-error' : 'is-success' }}">
        <i class="fa-solid {{ session('error') ? 'fa-circle-exclamation' : 'fa-circle-check' }}"></i>
        <span>{{ session('error') ?? session('success') }}</span>
        <button type="button" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>
@endif

{{-- $projectCards, $workSummary, $needsAttention all come pre-computed
     from DashboardController::lobby() -- deep-eager-loaded there so every
     stat here reflects real data with no extra queries per card. Greeting/
     search/quick-actions live in the navbar's center slot now (see the top
     of this file), not a separate header row inside the page content. --}}

{{-- ============ ATLAS: one full-bleed map canvas for the whole lobby,
     with the summary/legend/project-strip floating over it, instead of a
     Projects/Insights tab pair. See DashboardController::lobby() for
     $workSummary/$aoiMapPoints/$projectCards/$needsAttention. ============ --}}
<div class="atlas">
    <div id="overviewMap"
         class="atlas-map"
         data-aois='{{ json_encode($aoiMapPoints) }}'></div>

    @if($aoiMapPoints->isEmpty())
        <div class="atlas-map-empty">
            <span><i class="fa-solid fa-location-dot"></i></span>
            <h3>No surveyed areas to show</h3>
            <p>Once you confirm an area of interest, its location and name
               will be plotted right here.</p>
        </div>
    @endif

    {{-- ---------- summary card ---------- --}}
    <aside class="atlas-summary">
        @if($projectCards->isEmpty())
            {{-- First sign-in primer -- two real steps, not three: AOI
                 drawing and drone-mission planning are one merged
                 workflow step in this app (both happen on the same
                 'aoi' route), not the separate steps a generic 3-step
                 onboarding list would suggest. --}}
            <p class="atlas-eyebrow">Getting started</p>
            <h2 class="atlas-primer-title">Your surveyed areas will appear on this map</h2>
            <p class="atlas-primer-copy">Create a project, draw the area you want surveyed, and request a drone mission. Every area you confirm gets plotted here.</p>

            <ol class="atlas-primer-steps">
                <li>
                    <span class="atlas-primer-num">1</span>
                    <div><b>Create a project</b><span>Name it and invite your field team</span></div>
                </li>
                <li>
                    <span class="atlas-primer-num">2</span>
                    <div><b>Draw an area &amp; request a mission</b><span>Trace the boundary, then request the drone flight</span></div>
                </li>
            </ol>

            <button type="button" class="atlas-btn-solid atlas-primer-cta" onclick="window.location.href='{{ url('/projects/create') }}'">
                Create your first project
            </button>
        @else
            @php
                $nextUp = $projectCards->where('status', '!=', 'archived')->flatMap(function ($p) {
                    $rows = collect();
                    if ($p['aoi_count'] === 0) {
                        $rows->push(['project' => $p['name'], 'text' => 'Draw an area of interest', 'url' => route('projects.section', [$p['id'], 'aoi'])]);
                    }
                    if ($p['unanalyzed_orthomosaics'] > 0) {
                        $rows->push(['project' => $p['name'], 'text' => 'Classify ' . $p['unanalyzed_orthomosaics'] . ' ' . Str::plural('orthomosaic', $p['unanalyzed_orthomosaics']), 'url' => route('projects.section', [$p['id'], 'orthomosaic'])]);
                    }
                    if ($p['points_unassigned'] > 0) {
                        $rows->push(['project' => $p['name'], 'text' => 'Assign ' . $p['points_unassigned'] . ' ' . Str::plural('point', $p['points_unassigned']) . ' to a field researcher', 'url' => route('projects.section', [$p['id'], 'sampling-points'])]);
                    }
                    return $rows;
                })->values();
                $defaultTab = 0;
                $coverColors = ['bare_soil' => '#1EA03C', 'vegetation' => '#735F2D', 'open_water' => '#6482A0'];
            @endphp

            {{-- Manual tabs; the panel content below slides between views.
                 Overview is always the one that opens. --}}
            <div class="atlas-tabs" role="tablist" aria-label="Work summary views">
                <button type="button" role="tab" data-tab="0" @class(['is-active' => $defaultTab === 0])>Overview</button>
                <button type="button" role="tab" data-tab="1" @class(['is-active' => $defaultTab === 1])>Next up @if($nextUp->isNotEmpty())<em>{{ $nextUp->count() }}</em>@endif</button>
                <button type="button" role="tab" data-tab="2" @class(['is-active' => $defaultTab === 2])>Fieldwork</button>
                <button type="button" role="tab" data-tab="3" @class(['is-active' => $defaultTab === 3])>Trends</button>
            </div>

            <div class="atlas-panels" id="atlasPanels">
                <div class="atlas-track" id="atlasTrack" data-start="{{ $defaultTab }}">

                    <section class="atlas-panel" role="tabpanel">
                        <p class="atlas-eyebrow">Your work &middot; Davao Occidental</p>

                        <p class="atlas-hero">
                            <span class="atlas-hero-num" data-count="{{ $workSummary['area_hectares'] }}" data-decimal="1">0</span>
                            <span class="atlas-hero-unit">hectares mapped</span>
                        </p>

                        @if(!is_null($workSummary['suitability_rate']))
                            <div class="atlas-meter">
                                <div class="atlas-meter-track">
                                    <div class="atlas-meter-fill" style="width: {{ $workSummary['suitability_rate'] }}%"></div>
                                </div>
                                <span class="atlas-meter-val">{{ $workSummary['suitability_rate'] }}%</span>
                            </div>
                            <p class="atlas-meter-cap">of rated soil readings suitable for planting</p>
                        @else
                            <p class="atlas-meter-cap">No soil readings rated yet</p>
                        @endif

                        <div class="atlas-stats">
                            <div><b data-count="{{ $workSummary['projects'] }}">0</b><span>Projects</span></div>
                            <div><b data-count="{{ $workSummary['aois_mapped'] }}">0</b><span>AOIs</span></div>
                            <div><b data-count="{{ $workSummary['missions_flown'] }}">0</b><span>Missions</span></div>
                            <div><b data-count="{{ $workSummary['orthomosaics_uploaded'] }}">0</b><span>Orthos</span></div>
                            <div><b data-count="{{ $workSummary['classifications_completed'] }}">0</b><span>Classified</span></div>
                            <div><b data-count="{{ $workSummary['sampling_points'] }}">0</b><span>Points</span></div>
                            <div><b data-count="{{ $workSummary['soil_readings_collected'] }}">0</b><span>Readings</span></div>
                            <div><b data-count="{{ $workSummary['reports_generated'] }}">0</b><span>Reports</span></div>
                        </div>
                    </section>

                    <section class="atlas-panel" role="tabpanel">
                        <p class="atlas-eyebrow">Next up</p>
                        @if($nextUp->isEmpty())
                            <p class="atlas-empty-note"><i class="fa-solid fa-circle-check"></i> You are all caught up. Nothing is waiting on you right now.</p>
                        @else
                            <ul class="atlas-todo">
                                @foreach($nextUp->take(5) as $row)
                                    <li>
                                        <a href="{{ $row['url'] }}">
                                            <span><b>{{ $row['text'] }}</b><em>{{ $row['project'] }}</em></span>
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            @if($nextUp->count() > 5)
                                <p class="atlas-more">+{{ $nextUp->count() - 5 }} more</p>
                            @endif
                        @endif
                    </section>

                    <section class="atlas-panel" role="tabpanel">
                        <p class="atlas-eyebrow">Fieldwork</p>
                        @if($fieldProgress['total'] === 0)
                            <p class="atlas-empty-note">No sampling points yet. They appear after an orthomosaic is classified.</p>
                        @else
                            <p class="atlas-hero">
                                <span class="atlas-hero-num">{{ $fieldProgress['collected'] }}<small>/{{ $fieldProgress['total'] }}</small></span>
                                <span class="atlas-hero-unit">points collected</span>
                            </p>
                            <div class="atlas-stack" aria-hidden="true">
                                <i style="width: {{ $fieldProgress['collected'] / $fieldProgress['total'] * 100 }}%; background: var(--forest-green)"></i>
                                <i style="width: {{ $fieldProgress['assigned'] / $fieldProgress['total'] * 100 }}%; background: var(--sage-green)"></i>
                                <i style="width: {{ $fieldProgress['unassigned'] / $fieldProgress['total'] * 100 }}%; background: var(--border-soft)"></i>
                            </div>
                            <ul class="atlas-key">
                                <li><i style="background: var(--forest-green)"></i>{{ $fieldProgress['collected'] }} collected</li>
                                <li><i style="background: var(--sage-green)"></i>{{ $fieldProgress['assigned'] }} assigned</li>
                                <li><i style="background: var(--border-soft)"></i>{{ $fieldProgress['unassigned'] }} unassigned</li>
                            </ul>
                        @endif

                        <p class="atlas-subhead">Field team</p>
                        @if($fieldTeam->isEmpty())
                            <p class="atlas-empty-note">No field researchers yet.</p>
                        @else
                            <ul class="atlas-team">
                                @foreach($fieldTeam->take(4) as $member)
                                    <li>
                                        <span>{{ $member['name'] }}</span>
                                        <em>{{ $member['assigned'] }} assigned &middot; {{ $member['collected'] }} collected</em>
                                    </li>
                                @endforeach
                            </ul>
                            @if($fieldTeam->count() > 4)
                                <p class="atlas-more">+{{ $fieldTeam->count() - 4 }} more</p>
                            @endif
                        @endif
                    </section>

                    <section class="atlas-panel" role="tabpanel">
                        <p class="atlas-eyebrow">Rehabilitation trend</p>
                        @if($rehabProgress['comparisons_run'] === 0)
                            <p class="atlas-empty-note">No survey comparisons yet. Compare two orthomosaics of the same area to see whether a site is improving.</p>
                        @else
                            <div class="atlas-trend">
                                <div><b class="is-up">+{{ $rehabProgress['cells_improved'] }}</b><span>improved</span></div>
                                <div><b class="is-down">-{{ $rehabProgress['cells_declined'] }}</b><span>declined</span></div>
                                <div><b>{{ $rehabProgress['cells_unchanged'] }}</b><span>unchanged</span></div>
                            </div>
                            <p class="atlas-meter-cap">grid cells, across {{ $rehabProgress['comparisons_run'] }} {{ Str::plural('comparison', $rehabProgress['comparisons_run']) }}</p>
                        @endif

                        <p class="atlas-subhead">Land cover</p>
                        @if($totalCells === 0)
                            <p class="atlas-empty-note">No classified land yet.</p>
                        @else
                            <div class="atlas-stack" aria-hidden="true">
                                @foreach($suitabilityBreakdown as $row)
                                    <i style="width: {{ $row['percent'] }}%; background: {{ $coverColors[$row['cover']] ?? '#B4B4B4' }}"></i>
                                @endforeach
                            </div>
                            <ul class="atlas-key">
                                @foreach($suitabilityBreakdown as $row)
                                    <li><i style="background: {{ $coverColors[$row['cover']] ?? '#B4B4B4' }}"></i>{{ $row['label'] }} {{ $row['percent'] }}%</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </div>
        @endif
    </aside>

    {{-- ---------- legend (locations only -- no data layers here) ---------- --}}
    <div class="atlas-legend">
        <p class="atlas-legend-count">
            <i class="fa-solid fa-location-dot"></i>
            {{ $aoiMapPoints->count() }} surveyed {{ Str::plural('area', $aoiMapPoints->count()) }}
        </p>
        <ul>
            <li><i style="--pin:#2D6A4F"></i>Survey complete</li>
            <li><i style="--pin:#40916C"></i>In progress</li>
            <li><i style="--pin:#ADB5BD"></i>Not surveyed yet</li>
        </ul>
    </div>

    {{-- ---------- project strip ---------- --}}
    <section class="atlas-strip" id="atlasProjects">
        <header class="atlas-strip-head">
            <div class="atlas-strip-titles">
                <h2 class="atlas-chip-title">Your projects</h2>
                @if($projectCards->isNotEmpty())
                    <a class="atlas-viewall" href="{{ route('projects.index') }}">
                        View all projects
                        <span>{{ $projectCards->count() }}</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <div class="atlas-filters" id="atlasFilters">
                        <button type="button" class="is-active" data-filter="all" onclick="setFilter('all', this)">All {{ $projectCards->count() }}</button>
                        <button type="button" data-filter="active" onclick="setFilter('active', this)">Active {{ $projectCards->where('status', 'active')->count() }}</button>
                        <button type="button" data-filter="completed" onclick="setFilter('completed', this)">Completed {{ $projectCards->where('status', 'completed')->count() }}</button>
                        <button type="button" data-filter="archived" onclick="setFilter('archived', this)">Archived {{ $projectCards->where('status', 'archived')->count() }}</button>
                    </div>
                @else
                    <span class="atlas-filters"><button type="button" disabled>None yet</button></span>
                @endif
            </div>

            <div class="atlas-strip-tools">
                @if($projectCards->count() > 3)
                    <span class="atlas-hint">Scroll for older work</span>
                    <button type="button" class="atlas-arrow" data-rail-dir="-1" aria-label="Scroll left">
                        <i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="atlas-arrow" data-rail-dir="1" aria-label="Scroll right">
                        <i class="fa-solid fa-chevron-right"></i></button>
                @endif
            </div>
        </header>

        @if($projectCards->isEmpty())
            {{-- ---------- empty: one invitation row ---------- --}}
            <div class="atlas-empty-row">
                <span class="atlas-empty-icon"><i class="fa-solid fa-plus"></i></span>
                <div class="atlas-empty-copy">
                    <h3>You haven't created a project yet</h3>
                    <p>Projects hold your areas of interest, drone missions, classifications
                       and reports. Everything starts here.</p>
                </div>
            </div>
        @else
        <div class="atlas-rail" id="atlasRail">
            @php $phaseAccent = ['#ADB5BD', '#74C69D', '#40916C', '#40916C', '#2D6A4F']; @endphp
            @foreach($projectCards->sortByDesc('updated_at_raw') as $project)
                @php $isArchived = $project['status'] === 'archived'; @endphp
                <div class="atlas-pcard" data-status="{{ $project['status'] }}">
                    <a class="atlas-pcard-link" href="{{ route('projects.show', $project['id']) }}" aria-label="Open {{ $project['name'] }}" @if($isArchived) onclick="event.preventDefault(); openArchiveModal('{{ $project['id'] }}', @js($project['name']), 'archived')" @endif></a>
                    <i class="atlas-pcard-accent" style="background: {{ $isArchived ? '#ADB5BD' : $phaseAccent[$project['phase_index']] }}"></i>
                    <span class="atlas-pcard-thumb">
                        @if($project['aoi_shape_points'])
                            <svg viewBox="0 0 100 100" aria-hidden="true">
                                <polygon points="{{ $project['aoi_shape_points'] }}" />
                            </svg>
                        @else
                            <i class="fa-solid fa-draw-polygon"></i>
                        @endif
                    </span>
                    <span class="atlas-pcard-body">
                        <span class="atlas-pcard-top">
                            <b>{{ $project['name'] }}</b>
                        </span>
                        <em class="atlas-badge atlas-badge--{{ $project['status'] }}"><i></i>{{ $project['status'] }}</em>
                        <span class="atlas-pcard-meta">
                            <i class="fa-solid fa-draw-polygon"></i> {{ $project['aoi_count'] }} {{ Str::plural('AOI', $project['aoi_count']) }}
                            <span class="atlas-pcard-meta-sep">&middot;</span>
                            <i class="fa-regular fa-clock"></i> {{ $project['updated_at'] }}
                        </span>
                        <span class="atlas-stepper">
                            <span class="atlas-stepper-dots">
                                @foreach($project['phase_labels'] as $i => $label)
                                    <i @class([
                                        'atlas-dot',
                                        'is-done'    => $i <  $project['phase_index'],
                                        'is-current' => $i == $project['phase_index'],
                                    ])></i>
                                @endforeach
                            </span>
                            <span class="atlas-stepper-label">{{ $project['phase_labels'][$project['phase_index']] }}</span>
                        </span>
                    </span>
                    <div class="pcard-menu">
                        <button type="button" class="pcard-menu-btn" onclick="toggleCardMenu(this)" aria-haspopup="true" aria-expanded="false" aria-label="Project actions">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div class="pcard-menu-dropdown" hidden>
                            <button type="button" onclick="openRenameModal('{{ $project['id'] }}', @js($project['name']))"><i class="fa-solid fa-pen"></i> Rename</button>
                            <button type="button" onclick="openArchiveModal('{{ $project['id'] }}', @js($project['name']), '{{ $project['status'] }}')"><i class="fa-solid fa-box-archive"></i> {{ $isArchived ? 'Restore' : 'Archive' }}</button>
                            @if($isArchived)
                                <button type="button" class="pcard-menu-item--danger" onclick="openDeleteModal('{{ $project['id'] }}', @js($project['name']))"><i class="fa-solid fa-trash"></i> Delete Permanently</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            @unless($atProjectLimit)
                <button type="button" class="atlas-newcard" onclick="window.location.href='{{ url('/projects/create') }}'">
                    <i class="fa-solid fa-plus"></i> New project
                </button>
            @endunless
        </div>
        @endif
    </section>
</div>

<style>
    /* ============ DESIGN TOKENS ============
       A small, fixed scale, used consistently below instead of one-off
       values picked by eye (the previous version had ~15 different font
       sizes half a pixel apart and 6 different border-radii -- that
       inconsistency is a big part of why the page read as bland/uneven
       even after content changed). */
    :root {
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-pill: 999px;
        --space-2: 8px;
        --space-3: 12px;
        --space-4: 16px;
        --space-5: 24px;
        --space-6: 32px;
        --shadow-resting: 0 1px 2px hsl(var(--shadow-color) / 0.06), 0 3px 10px hsl(var(--shadow-color) / 0.06);
        --shadow-lifted: 0 10px 24px hsl(var(--shadow-color) / 0.16);
    }


    /* ============ MODALS (shared) ============ */
    /* ============ NAVBAR CENTER (this page only -- see the navbar-center
       section conditional in layouts/lobby.blade.php) ============
       Greeting and the two quick-action buttons live in the
       shared navbar's center slot on this page only, matching the design
       reference exactly (one integrated header row, not a separate
       summary card holding the greeting/actions). */
    .navbar-greeting {
        display: flex;
        flex-direction: column;
        line-height: 1.3;
        flex-shrink: 0;
    }

    .navbar-greeting-name {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 15px;
        color: var(--dark-gray);
    }

    .navbar-spacer {
        flex: 1;
    }

    /* ============ ATLAS ============
       One full-bleed map canvas for the whole lobby (not a tab among
       several) with the summary/legend/project-strip floating over it --
       from the "2a Atlas" design. Reuses this page's own tokens
       (--forest-green/--sage-green/--surface/--border-soft/etc, defined
       above in DESIGN TOKENS) instead of a separate --atlas-* palette, so
       dark mode ([data-theme="dark"], already wired up for the whole app
       via the navbar's theme toggle) applies automatically -- no
       duplicate light/dark token set to keep in sync. */
    .atlas {
        position: relative;
        height: calc(100vh - 78px);
        overflow: hidden;
        /* Edge to edge at any viewport width: this page opts out of the
           layout's 1600px cap and padding via the page-content-class
           section at the top of this file, so no negative-margin
           workaround is needed here. */
    }

    .atlas-map {
        position: absolute;
        inset: 0;
        z-index: 0;
        background: #3c4331;
    }

    .atlas-flash {
        position: absolute;
        left: 50%;
        top: 16px;
        z-index: 40;
        transform: translateX(-50%);
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: min(560px, calc(100vw - 32px));
        padding: 11px 14px;
        border-radius: 10px;
        background: var(--surface);
        box-shadow: var(--shadow-lifted);
        font-size: 13px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .atlas-flash.is-error i:first-child { color: #B42318; }
    .atlas-flash.is-success i:first-child { color: var(--forest-green); }

    .atlas-flash button {
        margin-left: 4px;
        background: none;
        color: var(--text-muted);
        font-size: 12px;
    }

    .atlas-map-empty {
        position: absolute;
        inset: 0;
        z-index: 10;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 0 24px 140px;
        background: rgba(27, 67, 50, .55);
        pointer-events: none;
    }

    .atlas-map-empty span {
        display: grid;
        place-items: center;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .14);
        border: 1px dashed rgba(255, 255, 255, .5);
        color: #fff;
        font-size: 24px;
    }

    .atlas-map-empty h3 {
        margin: 16px 0 0;
        font-family: 'Poppins', sans-serif;
        font-size: 22px;
        color: #fff;
    }

    .atlas-map-empty p {
        margin: 8px 0 0;
        max-width: 420px;
        font-size: 14px;
        line-height: 1.55;
        color: rgba(255, 255, 255, .92);
    }

    /* ---- floating summary ---- */
    .atlas-summary {
        position: absolute;
        left: 28px;
        top: 24px;
        z-index: 20;
        width: 336px;
        padding: 22px;
        background: var(--surface);
        border-radius: 18px;
        box-shadow: var(--shadow-lifted);
    }

    .atlas-eyebrow {
        margin: 0;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--teal);
    }

    .atlas-hero {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin: 10px 0 0;
    }

    .atlas-hero-num {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 40px;
        line-height: 1;
        color: var(--dark-gray);
    }

    .atlas-hero-unit {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .atlas-meter {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 16px;
    }

    .atlas-meter-track {
        flex: 1;
        height: 8px;
        border-radius: 999px;
        background: var(--border-soft);
        overflow: hidden;
    }

    .atlas-meter-fill {
        height: 100%;
        background: var(--teal);
    }

    .atlas-meter-val {
        font-size: 13px;
        font-weight: 700;
        color: var(--forest-green);
    }

    .atlas-meter-cap {
        margin: 6px 0 0;
        font-size: 12px;
        color: var(--text-muted);
    }

    .atlas-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        row-gap: 14px;
        margin-top: 18px;
        padding-top: 14px;
        border-top: 1px solid var(--border-soft);
    }

    .atlas-stats b {
        display: block;
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 19px;
        color: var(--dark-gray);
    }

    .atlas-stats span {
        font-size: 10.5px;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    /* ---- tabbed summary: manual tabs, panels slide horizontally ---- */
    .atlas-tabs {
        display: flex;
        gap: 4px;
        padding: 3px;
        margin-bottom: 16px;
        border-radius: 10px;
        background: var(--off-white);
        border: 1px solid var(--border-soft);
    }

    .atlas-tabs button {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 6px 4px;
        border-radius: 8px;
        background: transparent;
        font-size: 11.5px;
        font-weight: 600;
        color: var(--text-muted);
        white-space: nowrap;
    }

    .atlas-tabs button.is-active {
        background: var(--surface);
        color: var(--forest-green);
        box-shadow: var(--shadow-resting);
    }

    .atlas-tabs em {
        min-width: 16px;
        padding: 0 4px;
        border-radius: 999px;
        background: #B42318;
        font-size: 10px;
        font-style: normal;
        line-height: 16px;
        text-align: center;
        color: #fff;
    }

    .atlas-panels {
        overflow: hidden;
        transition: height .28s ease;
    }

    .atlas-track {
        display: flex;
        align-items: flex-start;
        transition: transform .28s ease;
    }

    .atlas-panel {
        flex: 0 0 100%;
        min-width: 0;
    }

    .atlas-hero-num small {
        font-size: 20px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .atlas-subhead {
        margin: 18px 0 8px;
        padding-top: 14px;
        border-top: 1px solid var(--border-soft);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .atlas-empty-note {
        margin: 10px 0 0;
        font-size: 12.5px;
        line-height: 1.5;
        color: var(--text-muted);
    }

    .atlas-empty-note i {
        margin-right: 4px;
        color: var(--teal);
    }

    .atlas-more {
        margin: 8px 0 0;
        font-size: 11.5px;
        color: var(--text-muted);
    }

    .atlas-todo {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin: 12px 0 0;
        list-style: none;
    }

    .atlas-todo a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid var(--border-soft);
        background: var(--off-white);
        text-decoration: none;
    }

    .atlas-todo a:hover {
        border-color: var(--sage-green);
    }

    .atlas-todo b {
        display: block;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .atlas-todo em {
        display: block;
        margin-top: 1px;
        font-size: 11.5px;
        font-style: normal;
        color: var(--text-muted);
    }

    .atlas-todo i {
        flex: none;
        font-size: 11px;
        color: var(--forest-green);
    }

    .atlas-stack {
        display: flex;
        height: 8px;
        margin-top: 14px;
        border-radius: 999px;
        overflow: hidden;
        background: var(--border-soft);
    }

    .atlas-stack i {
        display: block;
        height: 100%;
    }

    .atlas-key {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        margin: 10px 0 0;
        list-style: none;
        font-size: 11.5px;
        color: var(--dark-gray);
    }

    .atlas-key li {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .atlas-key li i {
        width: 9px;
        height: 9px;
        border-radius: 50%;
    }

    .atlas-team {
        display: flex;
        flex-direction: column;
        gap: 7px;
        margin: 0;
        list-style: none;
    }

    .atlas-team li {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .atlas-team em {
        font-size: 11.5px;
        font-style: normal;
        font-weight: 400;
        color: var(--text-muted);
    }

    .atlas-trend {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        margin-top: 12px;
    }

    .atlas-trend b {
        display: block;
        font-family: 'Poppins', sans-serif;
        font-size: 22px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .atlas-trend b.is-up { color: var(--teal); }
    .atlas-trend b.is-down { color: #B42318; }

    .atlas-trend span {
        font-size: 10.5px;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    /* ---- first sign-in primer (replaces the summary card's body when
       there are no projects yet) ---- */
    .atlas-primer-title {
        margin: 10px 0 0;
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 19px;
        line-height: 1.3;
        color: var(--dark-gray);
    }

    .atlas-primer-copy {
        margin: 8px 0 0;
        font-size: 13px;
        line-height: 1.5;
        color: var(--text-muted);
    }

    .atlas-primer-steps {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin: 18px 0 0;
        padding: 16px 0 0;
        border-top: 1px solid var(--border-soft);
    }

    .atlas-primer-steps li {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .atlas-primer-num {
        flex: none;
        width: 22px;
        height: 22px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: var(--forest-green);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }

    .atlas-primer-steps b {
        display: block;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .atlas-primer-steps span {
        display: block;
        margin-top: 2px;
        font-size: 12px;
        color: var(--text-muted);
    }

    .atlas-primer-cta {
        width: 100%;
        margin-top: 20px;
        text-align: center;
    }

    /* ---- legend ---- */
    .atlas-legend {
        position: absolute;
        right: 28px;
        top: 24px;
        z-index: 20;
        padding: 14px 16px;
        background: var(--surface);
        border-radius: 12px;
        box-shadow: var(--shadow-resting);
    }

    .atlas-legend-count {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-size: 12px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .atlas-legend-count i {
        color: var(--teal);
    }

    .atlas-legend ul {
        display: flex;
        flex-direction: column;
        gap: 7px;
        margin: 11px 0 0;
        padding: 11px 0 0;
        list-style: none;
        border-top: 1px solid var(--border-soft);
        font-size: 11.5px;
        color: var(--dark-gray);
    }

    .atlas-legend li {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .atlas-legend li i {
        width: 11px;
        height: 11px;
        border-radius: 50%;
        background: var(--pin);
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px var(--border-soft);
    }

    /* ---- project strip ---- */
    .atlas-strip {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 25;
        padding: 16px 28px 20px;
        background: linear-gradient(to top,
            var(--off-white) 58%,
            color-mix(in srgb, var(--off-white) 82%, transparent) 80%,
            transparent);
    }

    .atlas-strip-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .atlas-strip-titles {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* text over imagery: opaque pill, never a bare label */
    .atlas-chip-title {
        margin: 0;
        padding: 5px 12px;
        border-radius: 8px;
        background: var(--surface);
        box-shadow: var(--shadow-resting);
        font-family: 'Poppins', sans-serif;
        font-size: 15px;
        color: var(--dark-gray);
    }

    .atlas-filters {
        display: flex;
        gap: 6px;
    }

    .atlas-filters button {
        padding: 4px 11px;
        border-radius: 999px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        box-shadow: var(--shadow-resting);
        font-size: 11.5px;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
    }

    .atlas-filters button.is-active {
        background: var(--forest-green);
        border-color: var(--forest-green);
        color: #fff;
    }

    .atlas-filters button:disabled {
        cursor: default;
    }

    /* the rail: fixed-width cards, horizontal scroll, older work to the right */
    .atlas-rail {
        display: flex;
        gap: 14px;
        align-items: stretch;
        overflow-x: auto;
        padding: 2px 2px 10px;
        scrollbar-width: thin;
        scrollbar-color: #ADB5BD transparent;
        scroll-snap-type: x proximity;
    }

    .atlas-rail > * {
        scroll-snap-align: start;
    }

    .atlas-rail::-webkit-scrollbar {
        height: 8px;
    }

    .atlas-rail::-webkit-scrollbar-track {
        background: rgba(33, 37, 41, .06);
        border-radius: 999px;
    }

    .atlas-rail::-webkit-scrollbar-thumb {
        background: #ADB5BD;
        border-radius: 999px;
    }

    .atlas-rail::-webkit-scrollbar-thumb:hover {
        background: var(--text-muted);
    }

    .atlas-strip-tools {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .atlas-hint {
        padding: 5px 11px;
        border-radius: 8px;
        background: var(--surface);
        box-shadow: var(--shadow-resting);
        font-size: 11.5px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .atlas-arrow {
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 8px;
        background: var(--surface);
        box-shadow: var(--shadow-resting);
        color: var(--forest-green);
        font-size: 11px;
        cursor: pointer;
    }

    .atlas-arrow:disabled {
        color: var(--border-soft);
        cursor: default;
    }

    .atlas-viewall {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 13px;
        border-radius: 999px;
        background: var(--forest-green);
        box-shadow: var(--shadow-resting);
        font-size: 12px;
        font-weight: 600;
        color: #fff;
        text-decoration: none;
    }

    .atlas-viewall:hover {
        background: var(--forest-green-dark);
        color: #fff;
    }

    .atlas-viewall span {
        padding: 1px 7px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .22);
        font-size: 11px;
    }

    .atlas-viewall i {
        font-size: 10px;
    }

    /* ---- empty state (no projects at all) ---- */
    .atlas-empty-row {
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 22px 26px;
        border-radius: 14px;
        background: var(--surface);
        border: 1.5px dashed var(--sage-green);
        box-shadow: var(--shadow-resting);
    }

    .atlas-empty-icon {
        flex: none;
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: var(--forest-green);
        color: #fff;
        font-size: 17px;
    }

    .atlas-empty-copy {
        flex: 1;
        min-width: 0;
    }

    .atlas-empty-copy h3 {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        font-size: 15.5px;
        color: var(--dark-gray);
    }

    .atlas-empty-copy p {
        margin: 3px 0 0;
        font-size: 13px;
        color: var(--text-muted);
    }

    .atlas-btn-solid {
        padding: 11px 19px;
        border: 0;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        background: var(--forest-green);
        color: #fff;
    }

    .atlas-btn-solid:hover {
        background: var(--forest-green-dark);
    }

    /* ---- project cards on the rail ---- */
    .atlas-pcard {
        position: relative;
        overflow: hidden;
        flex: none;
        width: 330px;
        display: flex;
        gap: 14px;
        padding: 16px 16px 14px;
        border-radius: 16px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        box-shadow: var(--shadow-resting);
        color: inherit;
    }

    .atlas-pcard:hover {
        border-color: var(--sage-green);
        box-shadow: 0 14px 28px hsl(var(--shadow-color) / 0.14);
    }

    /* Thin strip along the top, colored by how far this project's phase
       has progressed (grey -> sage -> forest, same scale the lobby map's
       pins use) -- a project's status at a glance before reading anything. */
    .atlas-pcard-accent {
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        z-index: 1;
        pointer-events: none;
    }

    /* Stretched-link pattern: the card is a plain div so the "..." menu
       button below can be a real, non-nested <button> (a <button> inside
       an <a> is invalid HTML and behaves inconsistently for keyboard/
       screen-reader users). This invisible anchor covers the whole card
       and handles normal clicks; the menu sits above it (higher z-index)
       to intercept its own. */
    .atlas-pcard-link {
        position: absolute;
        inset: 0;
        z-index: 1;
        border-radius: inherit;
    }

    .atlas-pcard-thumb,
    .atlas-pcard-body {
        position: relative;
        z-index: 0;
    }

    /* ---- per-card "..." menu (rename/archive/delete) ---- */
    .pcard-menu {
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 2;
    }

    .pcard-menu-btn {
        width: 26px;
        height: 26px;
        display: grid;
        place-items: center;
        border-radius: 7px;
        background: transparent;
        color: var(--text-muted);
        font-size: 13px;
    }

    .pcard-menu-btn:hover,
    .pcard-menu-btn[aria-expanded="true"] {
        background: var(--off-white);
        color: var(--dark-gray);
    }

    .pcard-menu-dropdown {
        position: absolute;
        top: 30px;
        right: 0;
        z-index: 3;
        min-width: 168px;
        padding: 6px;
        border-radius: 10px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        box-shadow: var(--shadow-lifted);
    }

    .pcard-menu-dropdown button {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 9px;
        border-radius: 7px;
        background: transparent;
        text-align: left;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .pcard-menu-dropdown button:hover {
        background: var(--off-white);
    }

    .pcard-menu-dropdown button i {
        width: 14px;
        color: var(--text-muted);
    }

    .pcard-menu-dropdown button.pcard-menu-item--danger {
        color: #B42318;
    }

    .pcard-menu-dropdown button.pcard-menu-item--danger i {
        color: #B42318;
    }

    .atlas-pcard-thumb {
        flex: none;
        width: 60px;
        height: 60px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: linear-gradient(155deg, rgba(116, 198, 157, .22), rgba(45, 106, 79, .08));
        border: 1px solid rgba(116, 198, 157, .3);
        color: var(--sage-green);
        font-size: 20px;
        padding: 9px;
        box-sizing: border-box;
    }

    .atlas-pcard-thumb svg {
        width: 100%;
        height: 100%;
        overflow: visible;
    }

    .atlas-pcard-thumb polygon {
        fill: rgba(64, 145, 108, .28);
        stroke: var(--forest-green);
        stroke-width: 3;
        stroke-linejoin: round;
    }

    .atlas-pcard-body {
        display: flex;
        flex-direction: column;
        min-width: 0;
        justify-content: center;
        gap: 1px;
    }

    .atlas-pcard-top {
        display: flex;
        align-items: center;
    }

    .atlas-pcard-top b {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 15px;
        letter-spacing: -0.01em;
        color: var(--dark-gray);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .atlas-badge {
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 3px;
        padding: 2px 9px 2px 7px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        font-style: normal;
        letter-spacing: .03em;
        text-transform: uppercase;
        background: rgba(64, 145, 108, .12);
        color: var(--teal);
    }

    .atlas-badge i {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: currentColor;
    }

    .atlas-badge--archived {
        background: rgba(108, 117, 125, .14);
        color: var(--text-muted);
    }

    .atlas-pcard-meta {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 8px;
        font-size: 11.5px;
        color: var(--text-muted);
    }

    .atlas-pcard-meta i {
        font-size: 10px;
        opacity: .8;
    }

    .atlas-pcard-meta-sep {
        opacity: .6;
    }

    .atlas-stepper {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 11px;
    }

    .atlas-stepper-dots {
        position: relative;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Connecting line behind the dots so progress reads as one track
       instead of floating marks. Scoped to just the dots wrapper (not the
       trailing label span) so it always spans exactly the row of dots,
       whatever their count. */
    .atlas-stepper-dots::before {
        content: '';
        position: absolute;
        left: 3px;
        right: 3px;
        top: 50%;
        height: 1.5px;
        background: var(--border-soft);
        transform: translateY(-50%);
        z-index: 0;
    }

    .atlas-stepper-label {
        margin-left: 4px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .atlas-dot {
        position: relative;
        z-index: 1;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--surface);
        border: 1.5px solid var(--border-soft);
    }

    .atlas-dot.is-done {
        background: var(--forest-green);
        border-color: var(--forest-green);
    }

    .atlas-dot.is-current {
        width: 9px;
        height: 9px;
        background: var(--sage-green);
        border-color: var(--sage-green);
        box-shadow: 0 0 0 3px rgba(116, 198, 157, .25);
    }

    .atlas-newcard {
        flex: none;
        width: 150px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 1.5px dashed var(--sage-green);
        border-radius: 16px;
        background: rgba(116, 198, 157, .08);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--forest-green);
        cursor: pointer;
    }

    .atlas-newcard:hover {
        background: rgba(116, 198, 157, .18);
    }

    .atlas-newcard i {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--forest-green);
        color: #fff;
    }

    /* ---- pie marker + hover card (Leaflet) ---- */
    .lobby-map-pin {
        background: transparent;
        border: none;
    }

    .lobby-pin {
        display: block;
        cursor: pointer;
        filter: drop-shadow(0 2px 3px rgba(0, 0, 0, .45));
    }

    .leaflet-tooltip.aoi-label {
        padding: 5px 12px;
        border: 0;
        border-radius: 8px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 3px 10px rgba(0, 0, 0, .3);
        font: 600 12px 'Inter', sans-serif;
        color: #212529;
        white-space: nowrap;
    }

    .leaflet-tooltip.aoi-label::before {
        display: none;
    }

    /* Selectors are deliberately two classes deep: Leaflet ships
       .leaflet-popup-content p (margins) and .leaflet-container a (blue
       link color), which beat single-class rules and were breaking the
       spacing and the button text color. */
    .aoi-card .leaflet-popup-content-wrapper {
        padding: 0;
        border-radius: 16px;
        border: 1px solid var(--border-soft);
        box-shadow: 0 20px 44px rgba(33, 37, 41, .3);
        background: var(--surface);
        color: var(--dark-gray);
    }

    .aoi-card .leaflet-popup-content {
        margin: 0;
        padding: 18px 18px 16px;
        width: 290px !important;
        font-family: 'Inter', sans-serif;
        line-height: 1.3;
    }

    .aoi-card .leaflet-popup-tip {
        background: var(--surface);
    }

    .aoi-card .aoi-card-eyebrow {
        margin: 0;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--teal);
    }

    .aoi-card .aoi-card-title {
        margin: 4px 0 0;
        font-family: 'Poppins', sans-serif;
        font-size: 17px;
        font-weight: 600;
        line-height: 1.25;
        color: var(--dark-gray);
    }

    .aoi-card .aoi-card-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        margin-top: 14px;
        padding: 12px 0;
        border-top: 1px solid var(--border-soft);
        border-bottom: 1px solid var(--border-soft);
    }

    .aoi-card .aoi-card-stats span {
        display: block;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .aoi-card .aoi-card-stats b {
        display: block;
        margin-top: 2px;
        font-family: 'Poppins', sans-serif;
        font-size: 17px;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .aoi-card .aoi-card-stepper {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 12px;
    }

    .aoi-card .aoi-card-stepper span {
        margin-left: 4px;
        font-size: 12px;
        font-weight: 500;
        color: var(--text-muted);
    }

    .aoi-card a.aoi-card-btn {
        display: block;
        margin-top: 14px;
        padding: 10px;
        border-radius: 10px;
        background: var(--forest-green);
        text-align: center;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        color: #fff;
    }

    .aoi-card a.aoi-card-btn:hover {
        background: var(--forest-green-dark);
        color: #fff;
    }

    @media (max-width: 900px) {
        .atlas-summary {
            width: calc(100% - 56px);
        }

        .atlas-legend {
            display: none;
        }
    }

</style>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>
<script>
    // Atlas map: the lobby's whole canvas, always visible (no hidden-tab
    // sizing race to work around anymore -- it initializes straight into
    // a real, full-size container). Permanent name labels + a hover card
    // per AOI, colored by phase; falls back to a fixed regional view when
    // there's nothing to plot.
    (function () {
        var el = document.getElementById('overviewMap');
        if (!el || typeof L === 'undefined') return;

        var aois = JSON.parse(el.dataset.aois || '[]');

        var map = L.map(el, { zoomControl: false });
        L.control.zoom({ position: 'bottomright' }).addTo(map);

        L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                maxNativeZoom: 18,
                maxZoom: 20,
                attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics'
            }
        ).addTo(map);

        var PHASE_COLOR = ['#ADB5BD', '#74C69D', '#40916C', '#40916C', '#2D6A4F'];
        var bounds = [];

        aois.forEach(function (aoi) {
            var color = PHASE_COLOR[aoi.phase_index] || '#40916C';

            // Boundaries aren't drawn on the lobby map (pin + name only) --
            // they're kept purely as framing input so a lone AOI still
            // zooms to its real extent instead of a single-point max zoom.
            if (aoi.boundary) {
                bounds = bounds.concat(aoi.boundary);
            }

            var center = aoi.boundary
                ? L.polygon(aoi.boundary).getBounds().getCenter()
                : (aoi.center && aoi.center[0] && aoi.center[1] ? L.latLng(aoi.center[0], aoi.center[1]) : null);
            if (!center) return;
            bounds.push(center);

            var marker = L.marker(center, {
                icon: L.divIcon({
                    className: 'lobby-map-pin',
                    html: pinSvg(color),
                    iconSize: [32, 44], iconAnchor: [16, 44]
                })
            }).addTo(map);

            marker.bindTooltip(aoi.aoi_name, {
                permanent: true, direction: 'top', offset: [0, -46], className: 'aoi-label'
            });

            marker.bindPopup(cardHtml(aoi), {
                closeButton: false, offset: [0, -40], className: 'aoi-card'
            });
            marker.on('mouseover', function () { marker.openPopup(); });
            marker.on('popupopen', function (e) {
                marker.closeTooltip();
                e.popup.getElement().addEventListener('mouseleave', function () { marker.closePopup(); });
            });
            marker.on('popupclose', function () { marker.openTooltip(); });
        });

        if (bounds.length) {
            // Padding keeps pins clear of the floating summary card (left)
            // and project strip (bottom).
            map.fitBounds(L.latLngBounds(bounds).pad(.15), {
                paddingTopLeft: [380, 24],
                paddingBottomRight: [24, 220]
            });
        } else {
            map.setView([6.30, 125.58], 10);
        }
    })();

    // Google-Maps-style teardrop pin, tip anchored on the location.
    function pinSvg(color) {
        return '<svg class="lobby-pin" viewBox="0 0 32 44" width="32" height="44" aria-hidden="true">'
            + '<path d="M16 1C7.7 1 1 7.7 1 16c0 11.2 15 27 15 27s15-15.8 15-27C31 7.7 24.3 1 16 1z" fill="' + color + '" stroke="#fff" stroke-width="2"/>'
            + '<circle cx="16" cy="16" r="6" fill="#fff"/>'
            + '</svg>';
    }

    function cardHtml(aoi) {
        var dots = (aoi.phase_labels || []).map(function (_, i) {
            var cls = 'atlas-dot'
                + (i < aoi.phase_index ? ' is-done' : '')
                + (i === aoi.phase_index ? ' is-current' : '');
            return '<i class="' + cls + '"></i>';
        }).join('');

        return ''
            + '<p class="aoi-card-eyebrow">' + aoi.project_name + '</p>'
            + '<h4 class="aoi-card-title">' + aoi.aoi_name + '</h4>'
            + '<div class="aoi-card-stats">'
            +   '<div><span>Area</span><b>' + (aoi.area_hectares ?? '—') + ' ha</b></div>'
            +   '<div><span>Suitable</span><b>' + (aoi.suitability_rate ?? '—') + '%</b></div>'
            +   '<div><span>Points</span><b>' + (aoi.point_count ?? 0) + '</b></div>'
            + '</div>'
            + '<div class="aoi-card-stepper">' + dots + '<span>' + aoi.phase_label + '</span></div>'
            + '<a class="aoi-card-btn" href="/projects/' + aoi.project_id + '">Open project</a>';
    }
</script>
<script>
    // Rail scroll arrows -- tween scrollLeft ourselves rather than
    // scrollBy({behavior:'smooth'}), which is unreliable across browsers
    // when the container also has scroll-snap set.
    document.querySelectorAll('.atlas-strip-tools').forEach(function (tools) {
        var rail = tools.closest('.atlas-strip').querySelector('.atlas-rail');
        if (!rail) return;

        function sync() {
            var max = rail.scrollWidth - rail.clientWidth;
            var left = tools.querySelector('[data-rail-dir="-1"]');
            var right = tools.querySelector('[data-rail-dir="1"]');
            if (left) left.disabled = rail.scrollLeft <= 1;
            if (right) right.disabled = rail.scrollLeft >= max - 1;
        }

        tools.querySelectorAll('[data-rail-dir]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dir = Number(btn.dataset.railDir);
                var from = rail.scrollLeft;
                var max = rail.scrollWidth - rail.clientWidth;
                var to = Math.max(0, Math.min(max, from + dir * 688)); // two cards
                var t0 = performance.now();

                function step(now) {
                    var p = Math.min(1, (now - t0) / 280);
                    rail.scrollLeft = from + (to - from) * (1 - Math.pow(1 - p, 3));
                    if (p < 1) requestAnimationFrame(step); else sync();
                }
                requestAnimationFrame(step);
            });
        });

        rail.addEventListener('scroll', sync, { passive: true });
        sync();
    });
</script>
<script>
    // Summary card tabs: manual only. The track slides to the chosen panel
    // and the viewport height follows the active panel so the card never
    // keeps the tallest panel's empty space. Inactive panels are inert so
    // their links are not reachable by keyboard.
    (function () {
        var track = document.getElementById('atlasTrack');
        var viewport = document.getElementById('atlasPanels');
        if (!track || !viewport) return;

        var panels = track.querySelectorAll('.atlas-panel');
        var tabs = document.querySelectorAll('.atlas-tabs button');

        function show(index) {
            track.style.transform = 'translateX(-' + (index * 100) + '%)';
            panels.forEach(function (panel, i) { panel.inert = i !== index; });
            tabs.forEach(function (tab) {
                var active = Number(tab.dataset.tab) === index;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            viewport.style.height = panels[index].offsetHeight + 'px';
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () { show(Number(tab.dataset.tab)); });
        });

        var start = Number(track.dataset.start) || 0;
        track.style.transition = 'none';
        viewport.style.transition = 'none';
        show(start);
        requestAnimationFrame(function () {
            track.style.transition = '';
            viewport.style.transition = '';
        });
    })();
</script>
<script>
    // Default landing view is "All" -- matches the "All" filter pill
    // already marked .is-active in the HTML.
    let currentFilter = 'all';

    function applyFilters() {
        const cards = document.querySelectorAll('#atlasRail .atlas-pcard');
        cards.forEach(card => {
            const matchesStatus = currentFilter === 'all' || card.dataset.status === currentFilter;
            card.style.display = matchesStatus ? '' : 'none';
        });
    }

    function setFilter(status, el) {
        currentFilter = status;
        document.querySelectorAll('#atlasFilters button').forEach(btn => btn.classList.remove('is-active'));
        el.classList.add('is-active');
        applyFilters();
    }

    // Count-up animation for stat numbers (work summary card), runs once
    // on load. data-decimal lets a stat (hectares) count up with one
    // decimal place instead of snapping to whole numbers.
    function animateStats() {
        // Selects on the data attribute itself, not a class name -- a
        // class-based selector here already broke silently once (renamed
        // the CSS class during a redesign, forgot to update this line, and
        // every stat quietly stuck at its "0" placeholder).
        document.querySelectorAll('[data-count]').forEach(el => {
            const decimals = parseInt(el.dataset.decimal, 10) || 0;
            const target = parseFloat(el.dataset.count) || 0;
            const duration = 700;
            const start = performance.now();

            function tick(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = (eased * target).toFixed(decimals);
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });
    }

    document.addEventListener('DOMContentLoaded', animateStats);

</script>
@endsection
