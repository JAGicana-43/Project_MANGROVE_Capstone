@extends('layouts.lobby')

@section('title', 'Project Lobby — Project M.A.N.G.R.O.V.E')

@section('navbar-subtitle', 'Researcher Lobby')

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
        <span class="navbar-greeting-meta">{{ $projectCards->count() }} {{ Str::plural('project', $projectCards->count()) }} &middot; {{ $workSummary['aois_mapped'] }} {{ Str::plural('area', $workSummary['aois_mapped']) }} of interest</span>
    </div>

    <div class="navbar-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="atlasSearch" placeholder="Search projects or AOIs" autocomplete="off" onkeyup="searchProjects()">
    </div>

    <div class="navbar-quick-actions">
        <button type="button" class="btn-secondary" onclick="openFieldTechModal()">
            <i class="fa-solid fa-user-plus"></i>
            <span>Add Field Researcher</span>
        </button>
        <button type="button" class="btn-primary" onclick="window.location.href='{{ url('/projects/create') }}'">
            <i class="fa-solid fa-plus"></i>
            <span>New Project</span>
        </button>
    </div>
@endsection

@section('content')

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

@if($user->needsWelcomeTour())
    @include('partials.welcome-carousel')
@endif

{{-- $projectCards, $workSummary, $needsAttention all come pre-computed
     from DashboardController::lobby() -- deep-eager-loaded there so every
     stat here reflects real data with no extra queries per card. Greeting/
     search/quick-actions live in the navbar's center slot now (see the top
     of this file), not a separate header row inside the page content. --}}

<div class="modal-backdrop" id="fieldTechBackdrop" onclick="closeFieldTechModal()"></div>
<div class="modal-card" id="fieldTechModal">
    <div class="modal-header">
        <div>
            <h2>Add Field Researcher</h2>
            <p>The account will be active immediately &mdash; no admin approval needed.</p>
        </div>
        <button type="button" class="modal-close" onclick="closeFieldTechModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="fieldTechForm" onsubmit="submitFieldTech(event)" data-no-page-transition>
        @csrf
        <label>Full Name
            <input type="text" name="name" required>
        </label>
        <label>Email Address
            <input type="email" name="email" required>
        </label>
        <label>Password
            <input type="password" name="password" minlength="8" required>
        </label>
        <label>Confirm Password
            <input type="password" name="password_confirmation" minlength="8" required>
        </label>
        <p class="modal-error" id="fieldTechError" style="display:none;"></p>
        <p class="modal-success" id="fieldTechSuccess" style="display:none;"></p>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeFieldTechModal()">Cancel</button>
            <button type="submit" class="btn-primary" id="fieldTechSubmit">
                <i class="fa-solid fa-user-plus"></i>
                <span>Add Field Researcher</span>
            </button>
        </div>
    </form>
</div>

<div class="modal-backdrop" id="archiveModalBackdrop" onclick="closeArchiveModal()"></div>
<div class="modal-card" id="archiveModalCard">
    <div class="modal-header">
        <div>
            <h2 id="archiveModalTitle">Archive project?</h2>
            <p id="archiveModalSubtitle">This hides it from your active project list. You can restore it anytime from the Archived filter.</p>
        </div>
        <button type="button" class="modal-close" onclick="closeArchiveModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="archiveProjectForm" method="POST" data-no-page-transition>
        @csrf
        @method('PATCH')
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeArchiveModal()">Cancel</button>
            <button type="submit" class="btn-primary" id="archiveModalConfirmBtn">Archive</button>
        </div>
    </form>
</div>

<div class="modal-backdrop" id="renameModalBackdrop" onclick="closeRenameModal()"></div>
<div class="modal-card" id="renameModalCard">
    <div class="modal-header">
        <div>
            <h2>Rename project</h2>
        </div>
        <button type="button" class="modal-close" onclick="closeRenameModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="renameProjectForm" method="POST" data-no-page-transition>
        @csrf
        @method('PATCH')
        <label>Project name
            <input type="text" name="name" id="renameProjectInput" maxlength="255" required>
        </label>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeRenameModal()">Cancel</button>
            <button type="submit" class="btn-primary">Save</button>
        </div>
    </form>
</div>

<div class="modal-backdrop" id="deleteModalBackdrop" onclick="closeDeleteModal()"></div>
<div class="modal-card" id="deleteModalCard">
    <div class="modal-header">
        <div>
            <h2>Permanently delete project?</h2>
            <p>This cannot be undone. Every AOI, drone mission, orthomosaic, classification result, and report for
                "<strong id="deleteModalProjectName"></strong>" will be erased, including uploaded files on disk.</p>
        </div>
        <button type="button" class="modal-close" onclick="closeDeleteModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="deleteProjectForm" method="POST" data-no-page-transition>
        @csrf
        @method('DELETE')
        <label>Type the project name to confirm
            <input type="text" id="deleteConfirmInput" oninput="checkDeleteConfirmInput()" autocomplete="off" required>
        </label>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="submit" class="btn-primary btn-danger" id="deleteConfirmBtn" disabled>Delete Permanently</button>
        </div>
    </form>
</div>

{{-- ============ ATLAS: one full-bleed map canvas for the whole lobby,
     with the summary/legend/project-strip floating over it, instead of a
     Projects/Insights tab pair. See DashboardController::lobby() for
     $workSummary/$aoiMapPoints/$projectCards/$needsAttention. ============ --}}
@php
    $pendingOrthos = $needsAttention->sum('unanalyzed_orthomosaics');
    $pendingLabels = $needsAttention->sum('needs_labeling');
@endphp
<div class="atlas">
    <div id="overviewMap"
         class="atlas-map"
         data-aois='{{ json_encode($aoiMapPoints) }}'></div>

    @if($aoiMapPoints->isEmpty())
        <div class="atlas-map-empty">
            <span><i class="fa-solid fa-location-dot"></i></span>
            <h3>No surveyed areas to show</h3>
            <p>Once you confirm an area of interest, its outline and name
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
                <div><b data-count="{{ $workSummary['aois_mapped'] }}">0</b><span>AOIs</span></div>
                <div><b data-count="{{ $workSummary['orthomosaics_uploaded'] }}">0</b><span>Orthos</span></div>
                <div><b data-count="{{ $workSummary['classifications_completed'] }}">0</b><span>Classified</span></div>
                <div><b data-count="{{ $workSummary['sampling_points'] }}">0</b><span>Points</span></div>
            </div>

            @if($pendingOrthos > 0 || $pendingLabels > 0)
                <a class="atlas-alert" href="#atlasProjects">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>
                        @if($pendingOrthos > 0)
                            <strong>{{ $pendingOrthos }} {{ Str::plural('orthomosaic', $pendingOrthos) }}</strong> uploaded but never classified
                        @endif
                        @if($pendingOrthos > 0 && $pendingLabels > 0), @endif
                        @if($pendingLabels > 0)
                            <strong>{{ $pendingLabels }} {{ Str::plural('run', $pendingLabels) }}</strong> awaiting cluster labels
                        @endif
                    </span>
                </a>
            @endif
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

            @if($projectCards->count() > 3)
                <div class="atlas-strip-tools">
                    <span class="atlas-hint">Scroll for older work</span>
                    <button type="button" class="atlas-arrow" data-rail-dir="-1" aria-label="Scroll left">
                        <i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="atlas-arrow" data-rail-dir="1" aria-label="Scroll right">
                        <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            @endif
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
                <div class="atlas-empty-actions">
                    <button type="button" class="atlas-btn-solid" onclick="window.location.href='{{ url('/projects/create') }}'">
                        New project
                    </button>
                </div>
            </div>
        @else
        <div class="atlas-rail" id="atlasRail">
            @foreach($projectCards->sortByDesc('updated_at_raw') as $project)
                <a class="atlas-pcard" href="{{ route('projects.show', $project['id']) }}" data-status="{{ $project['status'] }}" data-name="{{ strtolower($project['name']) }}">
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
                            <em class="atlas-badge atlas-badge--{{ $project['status'] }}">{{ $project['status'] }}</em>
                        </span>
                        <span class="atlas-pcard-meta">
                            {{ $project['aoi_count'] }} {{ Str::plural('AOI', $project['aoi_count']) }}
                            &middot; updated {{ $project['updated_at'] }}
                        </span>
                        <span class="atlas-stepper">
                            @foreach($project['phase_labels'] as $i => $label)
                                <i @class([
                                    'atlas-dot',
                                    'is-done'    => $i <  $project['phase_index'],
                                    'is-current' => $i == $project['phase_index'],
                                ])></i>
                            @endforeach
                            <span>{{ $project['phase_labels'][$project['phase_index']] }}</span>
                        </span>
                    </span>
                </a>
            @endforeach

            <button type="button" class="atlas-newcard" onclick="window.location.href='{{ url('/projects/create') }}'">
                <i class="fa-solid fa-plus"></i> New project
            </button>
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
    .modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1400;
        background: rgba(20, 25, 22, 0.32);
        backdrop-filter: blur(2px);
    }

    .modal-card {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1500;
        width: min(420px, calc(100vw - 32px));
        max-height: calc(100vh - 48px);
        overflow-y: auto;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 12px;
        box-shadow: 0 24px 60px hsl(var(--shadow-color) / 0.22);
        padding: 24px;
    }

    .modal-backdrop.is-open, .modal-card.is-open { display: block; }

    .modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }

    .modal-header h2 {
        font-family: 'Poppins', sans-serif;
        font-size: 18px;
        color: var(--heading-accent);
        margin-bottom: 4px;
    }

    [data-theme="dark"] .modal-header h2 {
        color: var(--dark-gray);
    }

    .modal-header p {
        color: var(--text-muted);
        font-size: 12.5px;
    }

    .modal-close {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: rgba(33, 37, 41, 0.06);
        color: var(--dark-gray);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border: none;
    }

    #fieldTechForm, #renameProjectForm, #deleteProjectForm { display: flex; flex-direction: column; gap: 13px; }
    #fieldTechForm label, #renameProjectForm label, #deleteProjectForm label {
        display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; font-weight: 700; color: var(--text-muted);
    }
    #fieldTechForm input, #renameProjectForm input, #deleteProjectForm input {
        height: 42px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid var(--border-soft);
        font: inherit;
        font-size: 13.5px;
        color: var(--dark-gray);
        background: var(--surface);
    }
    #fieldTechForm input:focus, #renameProjectForm input:focus {
        outline: 2px solid rgba(116, 198, 157, 0.45);
        border-color: var(--forest-green);
    }
    #deleteProjectForm input:focus {
        outline: 2px solid rgba(180, 35, 24, 0.3);
        border-color: #B42318;
    }

    .modal-error {
        color: #C0392B;
        background: #FDEEEE;
        border: 1px solid #F3C9C9;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
    }

    .modal-success {
        color: #216346;
        background: #eef7f1;
        border: 1px solid #cfe8d7;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 4px;
    }

    /* ============ NAVBAR CENTER (this page only -- see the navbar-center
       section conditional in layouts/lobby.blade.php) ============
       Greeting, search, and the two quick-action buttons live in the
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

    .navbar-greeting-meta {
        font-size: 12px;
        color: var(--text-muted);
    }

    .navbar-search {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 160px;
        max-width: 340px;
        background: var(--off-white);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-sm);
        padding: 9px 14px;
    }

    .navbar-search i {
        color: var(--text-muted);
        font-size: 13px;
    }

    .navbar-search input {
        border: none;
        outline: none;
        background: transparent;
        font-size: 13px;
        font-family: 'Inter', sans-serif;
        width: 100%;
        color: var(--dark-gray);
    }

    .navbar-search input::placeholder {
        color: var(--text-muted);
    }

    .navbar-quick-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    @media (max-width: 1100px) {
        .navbar-search { display: none; }
    }

    @media (max-width: 900px) {
        .navbar-greeting-meta { display: none; }
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
        /* Cancels layouts/lobby.blade.php's .page-content padding
           (40px 40px 80px) so the map reaches the edges of its container
           instead of sitting inset inside the normal page padding --
           .page-content itself is full-width up to a 1600px cap, so this
           is edge-to-edge on any realistic viewport. */
        margin: -40px -40px -80px;
    }

    .atlas-map {
        position: absolute;
        inset: 0;
        z-index: 0;
        background: #3c4331;
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

    .atlas-alert {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 16px;
        padding: 12px 14px;
        border-radius: 12px;
        background: rgba(180, 35, 24, .07);
        font-size: 12.5px;
        line-height: 1.35;
        color: #B42318;
        text-decoration: none;
    }

    .atlas-alert:hover {
        background: rgba(180, 35, 24, .12);
        color: #B42318;
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

    .atlas-empty-actions {
        display: flex;
        gap: 10px;
        flex: none;
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
        flex: none;
        width: 330px;
        display: flex;
        gap: 14px;
        padding: 14px 16px;
        border-radius: 14px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        box-shadow: var(--shadow-resting);
        text-decoration: none;
        color: inherit;
    }

    .atlas-pcard:hover {
        border-color: var(--sage-green);
    }

    .atlas-pcard-thumb {
        flex: none;
        width: 62px;
        height: 62px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: rgba(116, 198, 157, .14);
        border: 1px solid rgba(116, 198, 157, .35);
        color: var(--sage-green);
        padding: 10px;
        box-sizing: border-box;
    }

    .atlas-pcard-thumb svg {
        width: 100%;
        height: 100%;
    }

    .atlas-pcard-thumb polygon {
        fill: rgba(64, 145, 108, .2);
        stroke: var(--forest-green);
        stroke-width: 3;
    }

    .atlas-pcard-body {
        display: flex;
        flex-direction: column;
        min-width: 0;
        justify-content: center;
    }

    .atlas-pcard-top {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .atlas-pcard-top b {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 14.5px;
        color: var(--dark-gray);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .atlas-badge {
        flex: none;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 600;
        font-style: normal;
        text-transform: uppercase;
        background: rgba(64, 145, 108, .12);
        color: var(--teal);
    }

    .atlas-badge--archived {
        background: rgba(108, 117, 125, .14);
        color: var(--text-muted);
    }

    .atlas-pcard-meta {
        margin-top: 3px;
        font-size: 12px;
        color: var(--text-muted);
    }

    .atlas-stepper {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 9px;
    }

    .atlas-stepper span {
        margin-left: 4px;
        font-size: 11px;
        color: var(--text-muted);
    }

    .atlas-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--border-soft);
    }

    .atlas-dot.is-done {
        background: var(--forest-green);
    }

    .atlas-dot.is-current {
        width: 10px;
        height: 10px;
        background: var(--sage-green);
    }

    .atlas-newcard {
        flex: none;
        width: 146px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: 1.5px dashed var(--sage-green);
        border-radius: 14px;
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
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--forest-green);
        color: #fff;
    }

    /* ---- pie marker + hover card (Leaflet) ---- */
    .lobby-map-pin {
        background: transparent;
        border: none;
    }

    .lobby-pie-marker {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
        cursor: pointer;
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

    .aoi-card .leaflet-popup-content-wrapper {
        padding: 0;
        border-radius: 16px;
        box-shadow: 0 20px 44px rgba(33, 37, 41, .3);
        background: var(--surface);
        color: var(--dark-gray);
    }

    .aoi-card .leaflet-popup-content {
        margin: 18px;
    }

    .aoi-card .leaflet-popup-tip {
        background: var(--surface);
    }

    .aoi-card-eyebrow {
        margin: 0;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .aoi-card-title {
        margin: 4px 0 0;
        font-family: 'Poppins', sans-serif;
        font-size: 17px;
        color: var(--dark-gray);
    }

    .aoi-card-stats {
        display: flex;
        gap: 22px;
        margin-top: 14px;
    }

    .aoi-card-stats span {
        display: block;
        font-size: 10.5px;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .aoi-card-stats b {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 18px;
        color: var(--dark-gray);
    }

    .aoi-card-stepper {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 16px;
    }

    .aoi-card-btn {
        display: block;
        margin-top: 16px;
        padding: 9px;
        border-radius: 9px;
        background: var(--forest-green);
        color: #fff;
        text-align: center;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }

    .aoi-card-btn:hover {
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

    /* Destructive variant, stacked on .btn-primary -- still used by the
       permanent-delete modal's confirm button. */
    .btn-primary.btn-danger {
        background: #B42318;
    }
    .btn-primary.btn-danger:hover {
        background: #8f1b13;
    }
    .btn-primary.btn-danger:disabled {
        background: rgba(180, 35, 24, 0.35);
        cursor: not-allowed;
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

            if (aoi.boundary) {
                L.polygon(aoi.boundary, {
                    color: color, weight: 2, fillColor: color, fillOpacity: .18
                }).addTo(map);
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
                    html: '<span class="lobby-pie-marker" style="--pin:' + color + ';background:' + color + '"></span>',
                    iconSize: [15, 15], iconAnchor: [7, 7]
                })
            }).addTo(map);

            marker.bindTooltip(aoi.aoi_name, {
                permanent: true, direction: 'top', offset: [0, -12], className: 'aoi-label'
            });

            marker.bindPopup(cardHtml(aoi), {
                closeButton: false, offset: [0, -8], minWidth: 296, className: 'aoi-card'
            });
            marker.on('mouseover', function () { marker.openPopup(); });
            marker.on('popupopen', function (e) {
                e.popup.getElement().addEventListener('mouseleave', function () { marker.closePopup(); });
            });
        });

        if (bounds.length) {
            map.fitBounds(L.latLngBounds(bounds).pad(.15));
        } else {
            map.setView([6.30, 125.58], 10);
        }
    })();

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
    // Default landing view is "All" -- matches the "All" filter pill
    // already marked .is-active in the HTML.
    let currentFilter = 'all';

    function applyFilters() {
        const query = (document.getElementById('atlasSearch')?.value || '').trim().toLowerCase();
        const cards = document.querySelectorAll('#atlasRail .atlas-pcard');
        cards.forEach(card => {
            const matchesStatus = currentFilter === 'all' || card.dataset.status === currentFilter;
            const matchesName = !query || card.dataset.name.includes(query);
            card.style.display = (matchesStatus && matchesName) ? '' : 'none';
        });
    }

    function setFilter(status, el) {
        currentFilter = status;
        document.querySelectorAll('#atlasFilters button').forEach(btn => btn.classList.remove('is-active'));
        el.classList.add('is-active');
        applyFilters();
    }

    // Navbar search box -- the same status-filtered project rail, further
    // narrowed by name. Lives in the shared navbar's center slot, so this
    // stays a plain global function it can call via onkeyup.
    function searchProjects() {
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

    // ============ ADD FIELD TECHNICIAN MODAL ============
    function openFieldTechModal() {
        document.getElementById('fieldTechBackdrop')?.classList.add('is-open');
        document.getElementById('fieldTechModal')?.classList.add('is-open');
    }

    function closeFieldTechModal() {
        document.getElementById('fieldTechBackdrop')?.classList.remove('is-open');
        document.getElementById('fieldTechModal')?.classList.remove('is-open');
        var errorEl = document.getElementById('fieldTechError');
        if (errorEl) errorEl.style.display = 'none';
        document.getElementById('fieldTechForm')?.reset();
    }

    function submitFieldTech(event) {
        event.preventDefault();

        var form = document.getElementById('fieldTechForm');
        var errorEl = document.getElementById('fieldTechError');
        var successEl = document.getElementById('fieldTechSuccess');
        var submitBtn = document.getElementById('fieldTechSubmit');
        var formData = new FormData(form);
        var password = formData.get('password');
        var confirmation = formData.get('password_confirmation');

        errorEl.style.display = 'none';
        successEl.style.display = 'none';

        if (password !== confirmation) {
            errorEl.textContent = 'Passwords do not match.';
            errorEl.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;

        fetch('{{ route('researcher.field-technicians.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': formData.get('_token'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                name: formData.get('name'),
                email: formData.get('email'),
                password: password,
                password_confirmation: confirmation
            })
        })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok) {
                    var firstError = result.data.errors ? Object.values(result.data.errors)[0][0] : (result.data.message || 'Could not create account.');
                    throw new Error(firstError);
                }
                form.reset();
                successEl.textContent = result.data.message;
                successEl.style.display = 'block';
                window.setTimeout(closeFieldTechModal, 1600);
            })
            .catch(function (error) {
                errorEl.textContent = error.message || 'Could not create account.';
                errorEl.style.display = 'block';
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    }

    // ============ ARCHIVE / RESTORE PROJECT MODAL ============
    function openArchiveModal(projectId, projectName, currentStatus) {
        var isArchived = currentStatus === 'archived';
        var titleEl = document.getElementById('archiveModalTitle');
        var subtitleEl = document.getElementById('archiveModalSubtitle');
        var confirmBtn = document.getElementById('archiveModalConfirmBtn');
        var form = document.getElementById('archiveProjectForm');

        if (titleEl) titleEl.textContent = (isArchived ? 'Restore "' : 'Archive "') + projectName + '"?';
        if (subtitleEl) {
            subtitleEl.textContent = isArchived
                ? 'This moves it back to your active project list. Nothing was ever deleted.'
                : 'This hides it from your active project list. You can restore it anytime from the Archived filter — nothing is deleted.';
        }
        if (confirmBtn) confirmBtn.textContent = isArchived ? 'Restore' : 'Archive';
        if (form) form.action = '/projects/' + encodeURIComponent(projectId) + '/archive';

        document.getElementById('archiveModalBackdrop')?.classList.add('is-open');
        document.getElementById('archiveModalCard')?.classList.add('is-open');
    }

    function closeArchiveModal() {
        document.getElementById('archiveModalBackdrop')?.classList.remove('is-open');
        document.getElementById('archiveModalCard')?.classList.remove('is-open');
    }

    // ============ RENAME PROJECT MODAL ============
    function openRenameModal(projectId, projectName) {
        var form = document.getElementById('renameProjectForm');
        var input = document.getElementById('renameProjectInput');

        if (form) form.action = '/projects/' + encodeURIComponent(projectId) + '/rename';
        if (input) input.value = projectName;

        document.getElementById('renameModalBackdrop')?.classList.add('is-open');
        document.getElementById('renameModalCard')?.classList.add('is-open');

        // Pre-select the name so typing immediately replaces it, same as
        // most "rename" affordances (Explorer, Finder, etc.) -- requires a
        // tick after the modal becomes visible/focusable in some browsers.
        window.setTimeout(function () {
            input?.focus();
            input?.select();
        }, 0);
    }

    function closeRenameModal() {
        document.getElementById('renameModalBackdrop')?.classList.remove('is-open');
        document.getElementById('renameModalCard')?.classList.remove('is-open');
    }

    // ============ PERMANENT DELETE PROJECT MODAL ============
    // Requires typing the exact project name to enable the confirm button --
    // this is a real, irreversible destructive action (erases uploaded
    // files on disk too, not just DB rows), so it gets more friction than
    // archive/rename.
    var deleteModalProjectName = '';

    function openDeleteModal(projectId, projectName) {
        deleteModalProjectName = projectName;
        var form = document.getElementById('deleteProjectForm');
        var nameEl = document.getElementById('deleteModalProjectName');
        var input = document.getElementById('deleteConfirmInput');
        var confirmBtn = document.getElementById('deleteConfirmBtn');

        if (form) form.action = '/projects/' + encodeURIComponent(projectId);
        if (nameEl) nameEl.textContent = projectName;
        if (input) input.value = '';
        if (confirmBtn) confirmBtn.disabled = true;

        document.getElementById('deleteModalBackdrop')?.classList.add('is-open');
        document.getElementById('deleteModalCard')?.classList.add('is-open');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModalBackdrop')?.classList.remove('is-open');
        document.getElementById('deleteModalCard')?.classList.remove('is-open');
    }

    function checkDeleteConfirmInput() {
        var input = document.getElementById('deleteConfirmInput');
        var confirmBtn = document.getElementById('deleteConfirmBtn');
        if (confirmBtn) confirmBtn.disabled = (input.value !== deleteModalProjectName);
    }
</script>
@endsection
