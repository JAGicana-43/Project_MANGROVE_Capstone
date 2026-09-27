@extends('layouts.lobby')

@section('title', 'All Projects — Project M.A.N.G.R.O.V.E')

@section('navbar-subtitle', 'All Projects')

@section('content')

@include('partials.project-action-modals')

{{-- Same card data DashboardController::lobby() feeds its Atlas rail with
     (ProjectCardBuilder), just laid out as a real wrapping grid instead of
     a horizontal-scroll strip -- the rail has no hidden cap, it just isn't
     browsable once there are more projects than fit on screen. --}}
@php
    $activeCount = $projectCards->where('status', 'active')->count();
    $completedCount = $projectCards->where('status', 'completed')->count();
    $archivedCount = $projectCards->where('status', 'archived')->count();
    $totalAois = $projectCards->sum('aoi_count');
@endphp

<div class="pidx-hero">
    <div class="pidx-hero-top">
        <div class="pidx-titles">
            <p class="pidx-eyebrow">Your work</p>
            <h1>All Projects</h1>
            <p class="pidx-titles-sub">{{ $projectCards->count() }} {{ Str::plural('project', $projectCards->count()) }} &middot; {{ $totalAois }} {{ Str::plural('area', $totalAois) }} of interest mapped</p>
        </div>

        <div class="pidx-actions">
            <a href="{{ route('researcher.lobby') }}" class="pidx-hero-btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Lobby</span>
            </a>
            <button type="button" class="pidx-hero-btn pidx-hero-btn--solid" @if($atProjectLimit) disabled title="Project limit reached ({{ $projectLimit }}). Delete a project to create a new one." @else onclick="window.location.href='{{ url('/projects/create') }}'" @endif>
                <i class="fa-solid fa-plus"></i>
                <span>New Project</span>
            </button>
        </div>
    </div>

    @if($projectCards->isNotEmpty())
        <div class="pidx-hero-stats">
            <div><b>{{ $activeCount }}</b><span>Active</span></div>
            <div><b>{{ $completedCount }}</b><span>Completed</span></div>
            <div><b>{{ $archivedCount }}</b><span>Archived</span></div>
            <div><b>{{ $projectLimit - $projectCards->where('is_creator', true)->count() }}</b><span>Slots left</span></div>
        </div>
    @endif
</div>

@if(session('error'))
    <p class="pidx-notice pidx-notice--error">{{ session('error') }}</p>
@elseif(session('success'))
    <p class="pidx-notice pidx-notice--success">{{ session('success') }}</p>
@elseif($atProjectLimit)
    <p class="pidx-notice">You've reached the limit of {{ $projectLimit }} projects. Delete a project to create a new one.</p>
@endif

@if($projectCards->isNotEmpty())
    <div class="pidx-toolbar">
        <div class="pidx-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="projectSearch" placeholder="Search projects" autocomplete="off" oninput="applyProjectFilters()">
        </div>

        <div class="pidx-filters" id="pidxFilters">
            <button type="button" class="is-active" data-filter="all" onclick="setProjectFilter('all', this)">All {{ $projectCards->count() }}</button>
            <button type="button" data-filter="active" onclick="setProjectFilter('active', this)">Active {{ $activeCount }}</button>
            <button type="button" data-filter="completed" onclick="setProjectFilter('completed', this)">Completed {{ $completedCount }}</button>
            <button type="button" data-filter="archived" onclick="setProjectFilter('archived', this)">Archived {{ $archivedCount }}</button>
        </div>
    </div>
@endif

@if($projectCards->isEmpty())
    <div class="pidx-empty">
        <span class="pidx-empty-icon"><i class="fa-solid fa-plus"></i></span>
        <div>
            <h3>You haven't created a project yet</h3>
            <p>Projects hold your areas of interest, drone missions, classifications and reports.</p>
        </div>
        <button type="button" class="pidx-btn-solid" onclick="window.location.href='{{ url('/projects/create') }}'">New project</button>
    </div>
@else
    <div class="pidx-grid" id="pidxGrid">
        @php $phaseAccent = ['#ADB5BD', '#74C69D', '#40916C', '#40916C', '#2D6A4F']; @endphp
        @foreach($projectCards as $project)
            @php $isArchived = $project['status'] === 'archived'; @endphp
            <div class="pidx-card" data-status="{{ $project['status'] }}" data-name="{{ strtolower($project['name']) }}">
                <a class="pidx-card-link" href="{{ route('projects.show', $project['id']) }}" aria-label="Open {{ $project['name'] }}" @if($isArchived) onclick="event.preventDefault(); openArchiveModal('{{ $project['id'] }}', @js($project['name']), 'archived')" @endif></a>
                <i class="pidx-card-accent" style="background: {{ $isArchived ? '#ADB5BD' : $phaseAccent[$project['phase_index']] }}"></i>
                <span class="pidx-card-thumb">
                    @if($project['aoi_shape_points'])
                        <svg viewBox="0 0 100 100" aria-hidden="true">
                            <polygon points="{{ $project['aoi_shape_points'] }}" />
                        </svg>
                    @else
                        <i class="fa-solid fa-draw-polygon"></i>
                    @endif
                </span>
                <span class="pidx-card-body">
                    <span class="pidx-card-top">
                        <b>{{ $project['name'] }}</b>
                    </span>
                    <em class="pidx-badge pidx-badge--{{ $project['status'] }}"><i></i>{{ $project['status'] }}</em>
                    <span class="pidx-card-meta">
                        <i class="fa-solid fa-draw-polygon"></i> {{ $project['aoi_count'] }} {{ Str::plural('AOI', $project['aoi_count']) }}
                        <span class="pidx-card-meta-sep">&middot;</span>
                        <i class="fa-regular fa-clock"></i> {{ $project['updated_at'] }}
                    </span>
                    <span class="pidx-stepper">
                        <span class="pidx-stepper-dots">
                            @foreach($project['phase_labels'] as $i => $label)
                                <i @class([
                                    'pidx-dot',
                                    'is-done'    => $i <  $project['phase_index'],
                                    'is-current' => $i == $project['phase_index'],
                                ])></i>
                            @endforeach
                        </span>
                        <span class="pidx-stepper-label">{{ $project['phase_labels'][$project['phase_index']] }}</span>
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
            <button type="button" class="pidx-newcard" onclick="window.location.href='{{ url('/projects/create') }}'">
                <i class="fa-solid fa-plus"></i> New project
            </button>
        @endunless
    </div>

    <p class="pidx-noresults" id="pidxNoResults" hidden>No projects match this filter/search.</p>
@endif

<style>
    /* ---- hero band: gives this page the same brand presence the lobby's
       Atlas map has, instead of a bare white header + list. Reuses the
       app's own forest-green tokens, so dark mode needs no separate
       treatment -- the gradient just runs between the existing tokens. */
    .pidx-hero {
        position: relative;
        overflow: hidden;
        margin: -40px -40px 28px;
        padding: 32px 40px 26px;
        background: linear-gradient(135deg, var(--forest-green) 0%, var(--forest-green-dark) 100%);
        color: #fff;
    }

    .pidx-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: .5;
        background-image:
            repeating-radial-gradient(circle at 8% 20%, transparent 0, transparent 24px, rgba(255,255,255,.05) 25px, transparent 26px),
            repeating-radial-gradient(circle at 96% 85%, transparent 0, transparent 30px, rgba(255,255,255,.05) 31px, transparent 32px);
        background-size: 380px 380px, 480px 480px;
    }

    .pidx-hero-top {
        position: relative;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .pidx-eyebrow {
        margin: 0;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .68);
    }

    .pidx-titles h1 {
        margin-top: 4px;
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 27px;
        color: #fff;
    }

    .pidx-titles-sub {
        margin-top: 5px;
        font-size: 13px;
        color: rgba(255, 255, 255, .78);
    }

    .pidx-hero-stats {
        position: relative;
        display: flex;
        gap: 28px;
        margin-top: 22px;
        padding-top: 18px;
        border-top: 1px solid rgba(255, 255, 255, .16);
    }

    .pidx-hero-stats b {
        display: block;
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 21px;
        color: #fff;
    }

    .pidx-hero-stats span {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .68);
    }

    .pidx-hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .28);
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        text-decoration: none;
        transition: transform 0.14s ease, background-color 0.2s ease;
    }

    .pidx-hero-btn:hover {
        background: rgba(255, 255, 255, .22);
    }

    .pidx-hero-btn:not(:disabled):active {
        transform: scale(0.96);
    }

    .pidx-hero-btn--solid {
        background: #fff;
        border-color: #fff;
        color: var(--forest-green-dark);
    }

    .pidx-hero-btn--solid:hover {
        background: rgba(255, 255, 255, .88);
    }

    .pidx-hero-btn--solid:disabled {
        opacity: .6;
        cursor: not-allowed;
        background: rgba(255, 255, 255, .7);
    }

    @media (max-width: 640px) {
        .pidx-hero {
            margin: -28px -16px 22px;
            padding: 24px 16px 20px;
        }

        .pidx-hero-stats {
            gap: 20px;
            flex-wrap: wrap;
        }
    }

    .pidx-notice {
        margin-bottom: 16px;
        padding: 10px 14px;
        border-radius: 8px;
        background: rgba(64, 145, 108, .1);
        border: 1px solid rgba(64, 145, 108, .3);
        font-size: 13px;
        color: var(--dark-gray);
    }

    .pidx-notice--error {
        background: rgba(180, 35, 24, .08);
        border-color: rgba(180, 35, 24, .3);
        color: #B42318;
    }

    .pidx-notice--success {
        background: rgba(45, 106, 79, .08);
        border-color: rgba(45, 106, 79, .3);
        color: var(--forest-green-dark);
    }

    .pidx-actions {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .pidx-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .pidx-search {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 220px;
        max-width: 360px;
        padding: 10px 14px;
        border-radius: 10px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        box-shadow: 0 1px 2px hsl(var(--shadow-color) / 0.05);
        transition: border-color 0.2s ease;
    }

    .pidx-search:focus-within {
        border-color: var(--sage-green);
    }

    .pidx-search i {
        color: var(--text-muted);
        font-size: 13px;
    }

    .pidx-search input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        color: var(--dark-gray);
    }

    .pidx-search input::placeholder {
        color: var(--text-muted);
    }

    .pidx-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .pidx-filters button {
        padding: 7px 14px;
        border-radius: 999px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        box-shadow: 0 1px 2px hsl(var(--shadow-color) / 0.05);
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        transition: transform 0.14s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .pidx-filters button:hover:not(.is-active) {
        border-color: var(--sage-green);
        color: var(--dark-gray);
    }

    .pidx-filters button:active {
        transform: scale(0.96);
    }

    .pidx-filters button.is-active {
        background: var(--forest-green);
        border-color: var(--forest-green);
        color: #fff;
    }

    .pidx-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
    }

    .pidx-card {
        position: relative;
        overflow: hidden;
        display: flex;
        gap: 14px;
        padding: 18px 16px 14px;
        border-radius: 16px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        box-shadow: 0 1px 2px hsl(var(--shadow-color) / 0.06), 0 3px 10px hsl(var(--shadow-color) / 0.06);
        color: inherit;
    }

    .pidx-card:hover {
        border-color: var(--sage-green);
        box-shadow: 0 14px 28px hsl(var(--shadow-color) / 0.14);
    }

    /* Thin strip along the top, colored by how far this project's phase
       has progressed -- same scale the lobby map's pins use. */
    .pidx-card-accent {
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        z-index: 1;
        pointer-events: none;
    }

    /* Stretched-link pattern -- see the matching comment in
       dashboard/lobby.blade.php's .atlas-pcard-link for why this isn't a
       <button> nested inside an <a>. */
    .pidx-card-link {
        position: absolute;
        inset: 0;
        z-index: 1;
        border-radius: inherit;
    }

    .pidx-card-thumb,
    .pidx-card-body {
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
        box-shadow: 0 20px 44px hsl(var(--shadow-color) / 0.22);
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

    .pidx-card-thumb {
        flex: none;
        width: 58px;
        height: 58px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: linear-gradient(155deg, rgba(116, 198, 157, .22), rgba(45, 106, 79, .08));
        border: 1px solid rgba(116, 198, 157, .3);
        color: var(--sage-green);
        font-size: 19px;
        padding: 9px;
        box-sizing: border-box;
    }

    .pidx-card-thumb svg { width: 100%; height: 100%; overflow: visible; }

    .pidx-card-thumb polygon {
        fill: rgba(64, 145, 108, .28);
        stroke: var(--forest-green);
        stroke-width: 3;
        stroke-linejoin: round;
    }

    .pidx-card-body {
        display: flex;
        flex-direction: column;
        min-width: 0;
        justify-content: center;
        gap: 1px;
    }

    .pidx-card-top {
        display: flex;
        align-items: center;
    }

    .pidx-card-top b {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 15px;
        letter-spacing: -0.01em;
        color: var(--dark-gray);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pidx-badge {
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

    .pidx-badge i {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: currentColor;
    }

    .pidx-badge--archived {
        background: rgba(108, 117, 125, .14);
        color: var(--text-muted);
    }

    .pidx-card-meta {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 8px;
        font-size: 11.5px;
        color: var(--text-muted);
    }

    .pidx-card-meta i {
        font-size: 10px;
        opacity: .8;
    }

    .pidx-card-meta-sep {
        opacity: .6;
    }

    .pidx-stepper {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 11px;
    }

    .pidx-stepper-dots {
        position: relative;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .pidx-stepper-dots::before {
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

    .pidx-stepper-label {
        margin-left: 4px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .pidx-dot {
        position: relative;
        z-index: 1;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--surface);
        border: 1.5px solid var(--border-soft);
    }

    .pidx-dot.is-done { background: var(--forest-green); border-color: var(--forest-green); }
    .pidx-dot.is-current {
        width: 9px;
        height: 9px;
        background: var(--sage-green);
        border-color: var(--sage-green);
        box-shadow: 0 0 0 3px rgba(116, 198, 157, .25);
    }

    .pidx-newcard {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 92px;
        border: 1.5px dashed var(--sage-green);
        border-radius: 14px;
        background: rgba(116, 198, 157, .08);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--forest-green);
        cursor: pointer;
    }

    .pidx-newcard:hover { background: rgba(116, 198, 157, .18); }

    .pidx-newcard i {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--forest-green);
        color: #fff;
    }

    .pidx-noresults {
        margin-top: 22px;
        text-align: center;
        font-size: 13px;
        color: var(--text-muted);
    }

    .pidx-empty {
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 22px 26px;
        border-radius: 14px;
        background: var(--surface);
        border: 1.5px dashed var(--sage-green);
    }

    .pidx-empty-icon {
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

    .pidx-empty h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 15.5px;
        color: var(--dark-gray);
    }

    .pidx-empty p {
        margin-top: 3px;
        font-size: 13px;
        color: var(--text-muted);
    }

    .pidx-btn-solid {
        margin-left: auto;
        flex: none;
        padding: 11px 19px;
        border: 0;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        cursor: pointer;
        background: var(--forest-green);
        color: #fff;
    }
</style>
@endsection

@section('scripts')
<script>
    let pidxFilter = 'all';

    function applyProjectFilters() {
        const query = (document.getElementById('projectSearch')?.value || '').trim().toLowerCase();
        const cards = document.querySelectorAll('#pidxGrid .pidx-card');
        let visibleCount = 0;
        cards.forEach(card => {
            const matchesStatus = pidxFilter === 'all' || card.dataset.status === pidxFilter;
            const matchesName = !query || card.dataset.name.includes(query);
            const visible = matchesStatus && matchesName;
            card.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });
        const noResults = document.getElementById('pidxNoResults');
        if (noResults) noResults.hidden = visibleCount > 0;
    }

    function setProjectFilter(status, el) {
        pidxFilter = status;
        document.querySelectorAll('#pidxFilters button').forEach(btn => btn.classList.remove('is-active'));
        el.classList.add('is-active');
        applyProjectFilters();
    }
</script>
@endsection
