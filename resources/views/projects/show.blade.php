@extends('layouts.lobby')

@section('title', $project->name . ' - Project MANGROVE')

@section('content')
<div class="project-header">
    <div>
        <a href="{{ route('researcher.lobby') }}" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Lobby</span>
        </a>
        <h1>{{ $project->name }}</h1>
        <p>{{ $project->description ?: 'No project description has been added yet.' }}</p>
    </div>
    <span class="status-badge status-{{ $project->status }}">{{ ucfirst($project->status) }}</span>
</div>

<div class="summary-grid">
    <div class="summary-tile">
        <i class="fa-solid fa-draw-polygon"></i>
        <span class="summary-value">{{ $project->aoi_count }}</span>
        <span class="summary-label">{{ \Illuminate\Support\Str::plural('AOI', $project->aoi_count) }}</span>
    </div>
    <div class="summary-tile">
        <i class="fa-solid fa-users"></i>
        <span class="summary-value">{{ $project->member_count }}</span>
        <span class="summary-label">{{ \Illuminate\Support\Str::plural('Member', $project->member_count) }}</span>
    </div>
    <div class="summary-tile">
        <i class="fa-regular fa-calendar"></i>
        <span class="summary-value">{{ $project->created_at->format('M d, Y') }}</span>
        <span class="summary-label">Created</span>
    </div>
</div>

<style>
    .project-header {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        align-items: flex-start;
        margin-bottom: 28px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--teal);
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 18px;
    }

    .project-header h1 {
        color: var(--forest-green-dark);
        font-size: 30px;
        margin-bottom: 8px;
    }

    .project-header p {
        color: var(--text-muted);
        max-width: 720px;
        line-height: 1.55;
    }

    .status-badge {
        flex-shrink: 0;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        padding: 5px 12px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .status-active {
        background: rgba(45, 106, 79, 0.12);
        color: var(--forest-green);
    }

    .status-completed {
        background: rgba(59, 130, 246, 0.12);
        color: #2563EB;
    }

    .status-archived {
        background: rgba(108, 117, 125, 0.12);
        color: var(--text-muted);
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .summary-tile {
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: #fff;
        border: 1px solid var(--border-soft);
        border-radius: 8px;
        padding: 22px;
    }

    .summary-tile i {
        color: var(--teal);
        font-size: 20px;
    }

    .summary-value {
        color: var(--forest-green-dark);
        font: 600 24px 'Poppins', sans-serif;
    }

    .summary-label {
        color: var(--text-muted);
        font-size: 14px;
    }

    @media (max-width: 720px) {
        .project-header {
            flex-direction: column;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection
