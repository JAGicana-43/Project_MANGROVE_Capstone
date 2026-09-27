@extends('layouts.workspace')

@php
    $sectionConfig = $config ?? [
        'title' => $title ?? 'Workspace Section',
        'subtitle' => $subtitle ?? 'Manage this section for the active project.',
        'icon' => $icon ?? 'fa-folder-open',
        'emptyMessage' => $emptyMessage ?? 'No data is available for this project yet.',
    ];
@endphp

@section('title', $sectionConfig['title'] ?? 'Workspace Section')

@section('content')
<div class="section-shell">
    <div class="section-header">
        <div>
            <h1><i class="fa-solid {{ $sectionConfig['icon'] ?? 'fa-folder-open' }}"></i> {{ $sectionConfig['title'] ?? 'Workspace Section' }}</h1>
            <p>{{ $sectionConfig['subtitle'] ?? 'Manage this section for the active project.' }}</p>
        </div>
    </div>

    <div class="section-card">
        @php
            $itemCount = ($items ?? collect())->count();
            $isCreateView = request()->routeIs('projects.create-section');
        @endphp
        <div class="section-summary">
            <div class="summary-pill">
                <i class="fa-solid {{ $sectionConfig['icon'] ?? 'fa-folder-open' }}"></i>
                <span>{{ $isCreateView ? 'Create' : $itemCount }} {{ $isCreateView ? 'New' : Str::plural('item', $itemCount) }}</span>
            </div>
            @if(!$isCreateView)
                <a href="{{ isset($project) && !empty($project->id) ? route('projects.create-section', ['project' => $project->id, 'section' => $section ?? 'aoi']) : '#' }}" class="action-btn">
                    <i class="fa-solid fa-plus"></i> Add {{ $sectionConfig['title'] ?? 'Item' }}
                </a>
            @endif
        </div>
        @if($isCreateView)
            <div class="create-panel">
                <div class="create-icon"><i class="fa-solid {{ $sectionConfig['icon'] ?? 'fa-folder-open' }}"></i></div>
                <h3>Create {{ $sectionConfig['title'] ?? 'Item' }}</h3>
                <p>{{ $sectionConfig['subtitle'] ?? 'This workspace is ready for a new entry.' }}</p>
                <div class="create-actions">
                    <a href="{{ route('projects.section', ['project' => $project->id, 'section' => $section ?? 'aoi']) }}" class="secondary-btn">
                        <i class="fa-solid fa-arrow-left"></i> Back to list
                    </a>
                    <button type="button" class="action-btn">
                        <i class="fa-solid fa-floppy-disk"></i> Save {{ $sectionConfig['title'] ?? 'Item' }}
                    </button>
                </div>
            </div>
        @elseif($itemCount > 0)
            <div class="items-grid">
                @foreach(($items ?? collect()) as $item)
                    <div class="item-card">
                        <div class="item-title-row">
                            <h3>
                                @if($section === 'aoi')
                                    {{ $item->name ?? 'Unnamed area' }}
                                @elseif($section === 'orthomosaic')
                                    {{ $item->file_name ?? $item->file_path ?? 'Orthomosaic #' . $loop->iteration }}
                                @elseif($section === 'sampling-points')
                                    {{ 'Sampling Point #' . ($loop->iteration) }}
                                @elseif($section === 'soil-readings')
                                    {{ 'Soil Reading #' . ($loop->iteration) }}
                                @elseif($section === 'reports')
                                    {{ $item->title ?? 'Project Report' }}
                                @else
                                    {{ $item->name ?? 'Item' }}
                                @endif
                            </h3>
                            <span class="status-pill">
                                {{ $item->status ?? ($item->report_type ?? 'active') }}
                            </span>
                        </div>
                        <div class="item-meta">
                            @if($section === 'aoi')
                                <span><i class="fa-solid fa-ruler-combined"></i> {{ $item->area_sqm ? number_format($item->area_sqm, 0) . ' sqm' : 'Area pending' }}</span>
                            @elseif($section === 'orthomosaic')
                                <span><i class="fa-solid fa-layer-group"></i> {{ $item->file_format ?? 'File pending' }}</span>
                                @php $runCount = $item->classificationRuns?->count() ?? 0; @endphp
                                <span>
                                    <i class="fa-solid fa-object-group"></i>
                                    {{ $runCount > 0 ? $runCount . ' classification ' . Str::plural('run', $runCount) : 'Classification pending' }}
                                </span>
                            @elseif($section === 'sampling-points')
                                <span><i class="fa-solid fa-location-dot"></i> {{ $item->latitude ? number_format($item->latitude, 4) : 'Pending' }}</span>
                            @elseif($section === 'soil-readings')
                                <span><i class="fa-solid fa-flask"></i> {{ $item->ph_avg ? 'pH ' . number_format($item->ph_avg, 2) : 'Pending' }}</span>
                            @elseif($section === 'reports')
                                <span><i class="fa-solid fa-file-lines"></i> {{ $item->report_type ?? 'Report' }}</span>
                            @endif
                            <span><i class="fa-regular fa-calendar"></i> {{ $item->created_at?->format('M d, Y') ?? $item->generated_at?->format('M d, Y') ?? 'Pending' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <i class="fa-solid {{ $sectionConfig['icon'] ?? 'fa-folder-open' }}"></i>
                <p>{{ $sectionConfig['emptyMessage'] ?? 'No data is available for this project yet.' }}</p>
            </div>
        @endif
    </div>
</div>

<style>
    .section-shell { display: flex; flex-direction: column; gap: 20px; }
    .section-header { background: #fff; border-radius: 16px; padding: 24px 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section-header h1 { font-size: 24px; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    .section-header p { color: #6c757d; }
    .section-card { background: #fff; border-radius: 16px; padding: 24px 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section-summary { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
    .summary-pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(45,106,79,0.1); color: #2D6A4F; padding: 8px 12px; border-radius: 999px; font-weight: 600; font-size: 13px; }
    .action-btn, .secondary-btn { display: inline-flex; align-items: center; gap: 8px; border: none; padding: 9px 14px; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .action-btn { background: #2D6A4F; color: #fff; }
    .secondary-btn { background: #f1f3f5; color: #495057; }
    .create-panel { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px; padding: 24px 0; }
    .create-icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(45,106,79,0.1); color: #2D6A4F; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    .create-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 8px; }
    .items-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .item-card { border: 1px solid #e9ecef; border-radius: 12px; padding: 16px; background: #fafafa; }
    .item-title-row { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 10px; }
    .item-title-row h3 { font-size: 15px; color: #212529; }
    .status-pill { background: rgba(45,106,79,0.12); color: #2D6A4F; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
    .item-meta { display: flex; flex-direction: column; gap: 6px; color: #6c757d; font-size: 13px; }
    .item-meta i { margin-right: 6px; color: #74C69D; }
    .empty-state { display: flex; flex-direction: column; align-items: center; gap: 10px; color: #6c757d; padding: 24px 0; }
    .empty-state i { font-size: 28px; color: #74C69D; }
    @media (max-width: 768px) { .items-grid { grid-template-columns: 1fr; } }
</style>
@endsection
