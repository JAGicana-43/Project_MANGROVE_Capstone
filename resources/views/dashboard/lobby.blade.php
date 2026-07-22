@extends('layouts.lobby')

@section('title', 'Project Lobby — Project MANGROVE')

@section('content')

@php
    $projectCards = $projects->map(function ($project) use ($user) {
        // Real project members (via project_members -> users), not a
        // stand-in built from splitting the creator's name into letters.
        // The creator is already a member (added with role 'lead' on
        // project creation — see ProjectController::store), so this
        // naturally includes them too.
        $members = $project->members
            ->map(fn ($member) => $member->user)
            ->filter()
            ->values();

        $memberChips = $members->take(3)->map(function ($member) {
            $name = trim($member->name ?? '');
            return [
                'name' => $name !== '' ? $name : 'Unknown user',
                'avatar_url' => $member->avatar_url,
                'initial' => $name !== '' ? strtoupper(substr($name, 0, 1)) : 'U',
            ];
        })->values();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status ?? 'active',
            'created_at' => $project->created_at ? $project->created_at->format('M d, Y') : 'Recently created',
            'updated_at' => $project->updated_at ? $project->updated_at->diffForHumans() : 'Recently updated',
            'aoi_count' => (int) ($project->aoi_count ?? 0),
            'member_count' => (int) ($project->member_count ?? 0),
            'members' => $memberChips,
            // Only the creator can archive/restore a project — mirrors the
            // creator-only checks already used for other project actions.
            'is_creator' => $project->created_by === $user->id,
        ];
    })->values();

    $hasProjects = $projectCards->isNotEmpty();
    $totalAois = $projectCards->sum('aoi_count');
    $totalMembers = $projectCards->sum('member_count');
    $activeCount = $projectCards->filter(fn ($project) => ($project['status'] ?? 'active') === 'active')->count();

    $currentHour = now('Asia/Manila')->hour;
    $greeting = match (true) {
        $currentHour < 12 => 'Good morning',
        $currentHour < 18 => 'Good afternoon',
        default => 'Good evening',
    };
@endphp

<div class="welcome-row">
    <div class="welcome-text">
        <h1>{{ $greeting }}, {{ $user->name ?? 'Researcher' }}</h1>
        <p>Select a project to continue or create a new one.</p>
    </div>
    <div class="welcome-actions">
        <button type="button" class="btn-secondary" onclick="openFieldTechModal()">
            <i class="fa-solid fa-user-plus"></i>
            <span>Add Field Technician</span>
        </button>
        <button type="button" class="btn-primary" onclick="window.location.href='{{ url('/projects/create') }}'">
            <i class="fa-solid fa-plus"></i>
            <span>New Project</span>
        </button>
    </div>
</div>

<div class="modal-backdrop" id="fieldTechBackdrop" onclick="closeFieldTechModal()"></div>
<div class="modal-card" id="fieldTechModal">
    <div class="modal-header">
        <div>
            <h2>Add Field Technician</h2>
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
                <span>Add Field Technician</span>
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

@if($hasProjects)
<div class="stats-bar">
    <div class="stat-item">
        <div class="stat-icon"><i class="fa-solid fa-folder-open"></i></div>
        <div class="stat-text">
            <span class="stat-number" data-count="{{ $projectCards->count() }}">0</span>
            <span class="stat-label">Total Projects</span>
        </div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-item">
        <div class="stat-icon stat-icon-active"><i class="fa-solid fa-satellite-dish"></i></div>
        <div class="stat-text">
            <span class="stat-number" data-count="{{ $activeCount }}">0</span>
            <span class="stat-label">Active Now</span>
        </div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-item">
        <div class="stat-icon"><i class="fa-solid fa-draw-polygon"></i></div>
        <div class="stat-text">
            <span class="stat-number" data-count="{{ $totalAois }}">0</span>
            <span class="stat-label">AOIs Mapped</span>
        </div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-item">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-text">
            <span class="stat-number" data-count="{{ $totalMembers }}">0</span>
            <span class="stat-label">Team Members</span>
        </div>
    </div>
</div>

<div class="toolbar-row">
    <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input
            type="text"
            id="projectSearch"
            placeholder="Search projects by name..."
            onkeyup="filterProjects()"
            autocomplete="off"
        >
    </div>

    <div class="filter-chips" id="filterChips">
        <button type="button" class="chip" data-filter="all" onclick="setFilter('all', this)">All</button>
        <button type="button" class="chip active" data-filter="active" onclick="setFilter('active', this)">Active</button>
        <button type="button" class="chip" data-filter="completed" onclick="setFilter('completed', this)">Completed</button>
        <button type="button" class="chip" data-filter="archived" onclick="setFilter('archived', this)">Archived</button>
    </div>

    <div class="sort-box">
        <i class="fa-solid fa-arrow-down-wide-short"></i>
        <select id="sortSelect" onchange="sortProjects()">
            <option value="newest">Newest first</option>
            <option value="name">Name A–Z</option>
        </select>
    </div>
</div>

<div class="project-grid" id="projectGrid">
    @foreach($projectCards as $index => $project)
        <div class="project-card"
             style="animation-delay: {{ $index * 70 }}ms"
             data-name="{{ strtolower($project['name']) }}"
             data-status="{{ $project['status'] }}"
             data-created="{{ $index }}">

            <div class="card-map">
                <svg viewBox="0 0 100 56" preserveAspectRatio="none">
                    <path d="M0,40 Q20,20 40,34 T80,28 T100,38 L100,56 L0,56 Z" class="map-fill-1"/>
                    <path d="M0,48 Q25,32 50,44 T100,42 L100,56 L0,56 Z" class="map-fill-2"/>
                </svg>
                <span class="card-map-pin"><i class="fa-solid fa-location-dot"></i></span>
            </div>

            <div class="card-body">
                <div class="card-top">
                    <h3 class="project-name">{{ $project['name'] }}</h3>
                    <div class="card-top-actions">
                        <span class="status-badge status-{{ $project['status'] }}">
                            {{ ucfirst($project['status']) }}
                        </span>
                        @if($project['is_creator'])
                            <button
                                type="button"
                                class="card-archive-btn"
                                title="{{ $project['status'] === 'archived' ? 'Restore project' : 'Archive project' }}"
                                onclick="openArchiveModal({{ Js::from($project['id']) }}, {{ Js::from($project['name']) }}, {{ Js::from($project['status']) }})"
                            >
                                <i class="fa-solid {{ $project['status'] === 'archived' ? 'fa-box-open' : 'fa-box-archive' }}"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="card-meta">
                    <div class="meta-item">
                        <i class="fa-regular fa-calendar"></i>
                        <span>Created {{ $project['created_at'] }}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fa-solid fa-draw-polygon"></i>
                        <span>{{ $project['aoi_count'] }} {{ Str::plural('AOI', $project['aoi_count']) }}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fa-regular fa-clock"></i>
                        <span>Updated {{ $project['updated_at'] }}</span>
                    </div>
                </div>

                <div class="card-footer">
                    <div class="avatar-stack">
                        @foreach($project['members'] as $member)
                            <span class="avatar-chip" title="{{ $member['name'] }}">
                                @if($member['avatar_url'])
                                    <img src="{{ $member['avatar_url'] }}" alt="{{ $member['name'] }}">
                                @else
                                    {{ $member['initial'] }}
                                @endif
                            </span>
                        @endforeach
                        @if($project['member_count'] > 3)
                            <span class="avatar-chip avatar-more">+{{ $project['member_count'] - 3 }}</span>
                        @endif
                    </div>

                    @if($project['status'] === 'archived')
                        <span class="archived-hint" title="Restore this project to open it again">
                            <i class="fa-solid fa-lock"></i>
                            <span>Archived</span>
                        </span>
                    @else
                        <a href="{{ url('/projects/'.$project['id']) }}" class="btn-secondary open-project-btn">
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>Open</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="no-results" id="noResults" style="display:none;">
    <i class="fa-solid fa-magnifying-glass"></i>
    <p>No projects match your search or filter.</p>
</div>

@else
<div class="empty-state">
    <div class="empty-icon">
        <i class="fa-solid fa-map-location-dot"></i>
    </div>
    <h2>No projects yet</h2>
    <p>Start mapping suitability zones by creating your first mangrove rehabilitation project.</p>
    <button type="button" class="btn-primary" onclick="window.location.href='{{ url('/projects/create') }}'">
        <i class="fa-solid fa-plus"></i>
        <span>Create your first project</span>
    </button>
</div>
@endif

<style>
    /* ============ WELCOME ROW ============ */
    .welcome-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 32px;
        flex-wrap: wrap;
    }

    .welcome-text h1 {
        font-size: 28px;
        font-weight: 600;
        color: var(--heading-accent);
        margin-bottom: 6px;
    }

    .welcome-text p {
        color: var(--text-muted);
        font-size: 15px;
    }

    .welcome-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    /* ============ ADD FIELD TECHNICIAN MODAL ============ */
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

    #fieldTechForm { display: flex; flex-direction: column; gap: 13px; }
    #fieldTechForm label { display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; font-weight: 700; color: var(--text-muted); }
    #fieldTechForm input {
        height: 42px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid var(--border-soft);
        font: inherit;
        font-size: 13.5px;
        color: var(--dark-gray);
        background: var(--surface);
    }
    #fieldTechForm input:focus {
        outline: 2px solid rgba(116, 198, 157, 0.45);
        border-color: var(--forest-green);
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

    /* ============ STATS BAR ============ */
    .stats-bar {
        display: flex;
        align-items: center;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        padding: 20px 28px;
        margin-bottom: 28px;
        box-shadow: 0 1px 3px hsl(var(--shadow-color) / 0.05);
        flex-wrap: wrap;
        gap: 20px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 14px;
        flex: 1;
        min-width: 140px;
    }

    .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: rgba(45, 106, 79, 0.1);
        color: var(--accent-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .stat-icon-active {
        background: rgba(64, 145, 108, 0.14);
        color: var(--teal);
    }

    .stat-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .stat-number {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 22px;
        color: var(--dark-gray);
    }

    .stat-label {
        font-size: 12px;
        color: var(--text-muted);
    }

    .stat-divider {
        width: 1px;
        align-self: stretch;
        background: var(--border-soft);
    }

    @media (max-width: 768px) {
        .stat-divider { display: none; }
        .stats-bar { gap: 16px 24px; }
    }

    /* ============ TOOLBAR (search + filter + sort) ============ */
    .toolbar-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .filter-chips {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .chip {
        background: var(--surface);
        border: 1px solid var(--border-soft);
        color: var(--text-muted);
        font-size: 13px;
        font-weight: 500;
        padding: 7px 16px;
        border-radius: 20px;
        transition: all 0.15s ease;
    }

    .chip:hover {
        border-color: var(--sage-green);
        color: var(--accent-green);
    }

    .chip.active {
        background: var(--forest-green);
        border-color: var(--forest-green);
        color: #fff;
    }

    .sort-box {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 10px;
        padding: 9px 14px;
    }

    .sort-box i {
        color: var(--text-muted);
        font-size: 12.5px;
    }

    .sort-box select {
        border: none;
        outline: none;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        color: var(--dark-gray);
        background: transparent;
    }

    /* ============ SEARCH ============ */
    .search-box {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 220px;
        max-width: 380px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 10px;
        padding: 11px 16px;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .search-box:focus-within {
        border-color: var(--sage-green);
        box-shadow: 0 0 0 3px rgba(116, 198, 157, 0.18);
    }

    .search-box i {
        color: var(--text-muted);
        font-size: 14px;
    }

    .search-box input {
        border: none;
        outline: none;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        width: 100%;
        color: var(--dark-gray);
        background: transparent;
    }

    .search-box input::placeholder {
        color: #ADB5BD;
    }

    /* ============ PROJECT GRID ============ */
    .project-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 22px;
    }

    @media (max-width: 992px) {
        .project-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .project-grid {
            grid-template-columns: 1fr;
        }
    }

    .project-card {
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 3px hsl(var(--shadow-color) / 0.06);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        opacity: 0;
        animation: cardIn 0.45s ease forwards;
    }

    @keyframes cardIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .project-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px hsl(var(--shadow-color) / 0.16);
        border-color: var(--sage-green);
    }

    .project-card:hover .card-map-pin {
        transform: translateY(-3px);
    }

    /* Mini map thumbnail — signature detail per card */
    .card-map {
        position: relative;
        height: 72px;
        background: linear-gradient(180deg, #EAF4EF, #F8F9FA);
        overflow: hidden;
    }

    .card-map svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    .map-fill-1 { fill: rgba(45, 106, 79, 0.16); }
    .map-fill-2 { fill: rgba(64, 145, 108, 0.22); }

    .card-map-pin {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: var(--forest-green);
        font-size: 18px;
        transition: transform 0.18s ease;
        filter: drop-shadow(0 2px 3px rgba(0,0,0,0.15));
    }

    .card-body {
        padding: 20px 22px 22px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        flex: 1;
    }

    .card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .project-name {
        font-size: 16.5px;
        font-weight: 600;
        color: var(--dark-gray);
        line-height: 1.35;
    }

    .card-top-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .card-archive-btn {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        cursor: pointer;
    }

    .card-archive-btn:hover {
        background: rgba(180, 35, 24, 0.1);
        border-color: rgba(180, 35, 24, 0.25);
        color: #B42318;
    }

    .status-badge {
        flex-shrink: 0;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .status-active {
        background: rgba(45, 106, 79, 0.12);
        color: var(--accent-green);
    }

    .status-completed {
        background: rgba(59, 130, 246, 0.12);
        color: #2563EB;
    }

    .status-archived {
        background: rgba(108, 117, 125, 0.12);
        color: var(--text-muted);
    }

    .card-meta {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 13.5px;
        color: var(--text-muted);
    }

    .meta-item i {
        width: 14px;
        color: var(--teal);
        font-size: 12.5px;
    }

    .card-footer {
        margin-top: auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .avatar-stack {
        display: flex;
    }

    .avatar-chip {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--sage-green);
        color: var(--forest-green-dark);
        font-size: 10.5px;
        font-weight: 700;
        font-family: 'Poppins', sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--surface);
        margin-left: -8px;
        overflow: hidden;
        flex: 0 0 auto;
    }

    .avatar-chip img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-chip:first-child {
        margin-left: 0;
    }

    .avatar-more {
        background: var(--border-soft);
        color: var(--text-muted);
    }

    .open-project-btn {
        justify-content: center;
        flex-shrink: 0;
    }

    .archived-hint {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        flex-shrink: 0;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--text-muted);
        padding: 9px 14px;
    }

    /* ============ EMPTY STATE ============ */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 100px 20px;
        max-width: 420px;
        margin: 0 auto;
    }

    .empty-icon {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: rgba(116, 198, 157, 0.15);
        color: var(--accent-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin-bottom: 22px;
    }

    .empty-state h2 {
        font-size: 20px;
        font-weight: 600;
        color: var(--dark-gray);
        margin-bottom: 8px;
    }

    .empty-state p {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 24px;
    }

    /* ============ NO SEARCH RESULTS ============ */
    .no-results {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .no-results i {
        font-size: 26px;
        margin-bottom: 12px;
        display: block;
    }
</style>
@endsection

@section('scripts')
<script>
    // Default landing view is "Active" only, so an archived project never
    // shows up unless the user explicitly clicks the "Archived" (or "All")
    // chip — matches the "Active" chip already marked active in the HTML.
    let currentFilter = 'active';

    function applyFilters() {
        const query = (document.getElementById('projectSearch')?.value || '').trim().toLowerCase();
        const cards = document.querySelectorAll('#projectGrid .project-card');
        const noResults = document.getElementById('noResults');
        let visibleCount = 0;

        cards.forEach(card => {
            const matchesName = card.dataset.name.includes(query);
            // The default landing filter is "Active", not "All" (see
            // currentFilter above) — an archived project only appears if the
            // user explicitly picks "All" or "Archived", so "All" here can
            // safely mean literally everything.
            const matchesStatus = currentFilter === 'all' || card.dataset.status === currentFilter;
            const show = matchesName && matchesStatus;
            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    function filterProjects() {
        applyFilters();
    }

    function setFilter(status, el) {
        currentFilter = status;
        document.querySelectorAll('#filterChips .chip').forEach(chip => chip.classList.remove('active'));
        el.classList.add('active');
        applyFilters();
    }

    function sortProjects() {
        const grid = document.getElementById('projectGrid');
        if (!grid) return;
        const sortBy = document.getElementById('sortSelect').value;
        const cards = Array.from(grid.querySelectorAll('.project-card'));

        cards.sort((a, b) => {
            if (sortBy === 'name') {
                return a.dataset.name.localeCompare(b.dataset.name);
            }
            // newest first
            return Number(b.dataset.created) - Number(a.dataset.created);
        });

        cards.forEach(card => grid.appendChild(card));
    }

    // Count-up animation for the stats bar, runs once on load
    function animateStats() {
        document.querySelectorAll('.stat-number').forEach(el => {
            const target = parseInt(el.dataset.count, 10) || 0;
            const duration = 700;
            const start = performance.now();

            function tick(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(eased * target);
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });
    }

    document.addEventListener('DOMContentLoaded', animateStats);

    // applyFilters() was previously only wired to search/chip-click events —
    // the default currentFilter ('active') never actually got applied until
    // the user interacted with something, so archived/completed projects
    // stayed visible on first load despite the "Active" chip being selected.
    document.addEventListener('DOMContentLoaded', applyFilters);

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
</script>
@endsection