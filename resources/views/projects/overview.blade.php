@extends('layouts.workspace')

@section('title', 'Overview')

@push('styles')
<style>
    /* ===== A. PROJECT HEADER ===== */
    .project-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        background: #fff;
        border-radius: 16px;
        padding: 28px 32px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 28px;
    }

    .project-header-left h1 {
        font-size: 26px;
        font-weight: 700;
        color: #212529;
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 10px;
    }

    .project-meta {
        color: #6c757d;
        font-size: 14px;
        margin-top: 8px;
        line-height: 1.6;
    }

    .project-meta i { color: #74C69D; margin-right: 6px; }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        font-weight: 600;
        padding: 6px 14px;
        border-radius: 20px;
    }

    .status-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
    .status-pill.active { background: rgba(45,106,79,0.12); color: #2D6A4F; }
    .status-pill.completed { background: rgba(43,108,176,0.12); color: #2b6cb0; }
    .status-pill.archived { background: rgba(108,117,125,0.12); color: #6c757d; }

    /* ===== B. WORKFLOW PROGRESS TRACKER ===== */
    .progress-tracker-card {
        background: #fff;
        border-radius: 16px;
        padding: 32px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 28px;
        overflow-x: auto;
    }

    .progress-tracker-card h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 26px;
        color: #212529;
    }

    .progress-tracker {
        display: flex;
        align-items: flex-start;
        min-width: 720px;
    }

    .progress-step {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        text-align: center;
    }

    .progress-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 18px;
        left: 55%;
        width: 90%;
        height: 3px;
        background: #e9ecef;
        z-index: 0;
    }

    .step-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 600;
        position: relative;
        z-index: 1;
        background: #fff;
        border: 3px solid #dee2e6;
        color: #adb5bd;
    }

    .step-circle.complete {
        background: #2D6A4F;
        border-color: #2D6A4F;
        color: #fff;
    }

    .step-circle.in-progress {
        background: #fff;
        border-color: #f0a500;
        color: #f0a500;
    }

    .step-circle.pending {
        background: #fff;
        border-color: #dee2e6;
        color: #ced4da;
    }

    .step-label {
        font-size: 12.5px;
        font-weight: 500;
        margin-top: 10px;
        color: #495057;
        max-width: 100px;
    }

    /* ===== C. MAP BANNER ===== */
    .map-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 28px;
    }

    .map-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .map-card-header h3 {
        font-size: 16px;
        font-weight: 600;
        color: #212529;
    }

    .map-wrapper {
        position: relative;
        height: 400px;
        width: 100%;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    }

    #overview-map {
        height: 100%;
        width: 100%;
    }

    .map-overlay {
        position: absolute;
        inset: 0;
        background: rgba(33, 37, 41, 0.55);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 500;
        pointer-events: none;
        padding: 20px;
    }

    .map-overlay-box {
        background: rgba(255,255,255,0.96);
        border-radius: 14px;
        padding: 24px 30px;
        text-align: center;
        max-width: 420px;
        pointer-events: auto;
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .map-overlay-box i {
        font-size: 26px;
        color: #f0a500;
        margin-bottom: 10px;
    }

    .map-overlay-box p {
        font-size: 14.5px;
        color: #495057;
        line-height: 1.5;
        margin-bottom: 14px;
    }

    .map-overlay-box a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #2D6A4F;
        color: #fff;
        padding: 9px 18px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        transition: background 0.2s;
    }

    .map-overlay-box a:hover { background: #40916C; }

    /* ===== D. KPI CARDS ===== */
    .kpi-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }

    .kpi-card {
        background: #fff;
        border-radius: 14px;
        padding: 22px 24px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.2s;
    }

    .kpi-card:hover { transform: translateY(-3px); }

    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        color: #fff;
        flex-shrink: 0;
    }

    .kpi-icon.aoi { background: #2D6A4F; }
    .kpi-icon.drone { background: #40916C; }
    .kpi-icon.sampling { background: #74C69D; }
    .kpi-icon.soil { background: #f0a500; }

    .kpi-value {
        font-size: 26px;
        font-weight: 700;
        color: #212529;
        font-family: 'Poppins', sans-serif;
        line-height: 1.1;
    }

    .kpi-label {
        font-size: 13px;
        color: #6c757d;
        margin-top: 2px;
    }

    /* ===== E. RECENT ACTIVITY ===== */
    .activity-card {
        background: #fff;
        border-radius: 16px;
        padding: 28px 32px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .activity-card h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 20px;
        color: #212529;
    }

    .activity-list { list-style: none; }

    .activity-item {
        display: flex;
        gap: 16px;
        padding: 14px 0;
        border-bottom: 1px solid #f1f3f5;
    }

    .activity-item:last-child { border-bottom: none; }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(45,106,79,0.1);
        color: #2D6A4F;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .activity-text {
        font-size: 14.5px;
        color: #212529;
        font-weight: 500;
    }

    .activity-time {
        font-size: 12.5px;
        color: #adb5bd;
        margin-top: 3px;
    }

    @media (max-width: 900px) {
        .kpi-row { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 560px) {
        .kpi-row { grid-template-columns: 1fr; }
        .project-header { flex-direction: column; }
    }
</style>
@endpush

@section('content')

    {{-- A. PROJECT HEADER --}}
    <div class="project-header">
        <div class="project-header-left">
            <h1>
                <i class="fa-solid fa-tree" style="color:#2D6A4F;"></i>
                {{ $project->name ?? 'Pujada Bay Mangrove Rehabilitation' }}
            </h1>
            <span class="status-pill {{ $project->status ?? 'active' }}">
                <span class="dot"></span>
                {{ ucfirst($project->status ?? 'Active') }}
            </span>
            <div class="project-meta">
                <div><i class="fa-regular fa-calendar"></i> Created on {{ $project->created_at ?? 'July 1, 2026' }}</div>
                <div style="margin-top:6px;">{{ $project->description ?? 'Monitoring and rehabilitation of mangrove forest cover along Pujada Bay, Davao Oriental, using drone-based aerial survey and ground-truth soil sampling.' }}</div>
            </div>
        </div>
    </div>

    {{-- B. WORKFLOW PROGRESS TRACKER --}}
    <div class="progress-tracker-card">
        <h3>Workflow Progress</h3>
        <div class="progress-tracker">
            @php
                $steps = [
                    ['label' => 'Area of Interest', 'status' => 'pending'],
                    ['label' => 'Drone Mission', 'status' => 'pending'],
                    ['label' => 'Orthomosaic', 'status' => 'pending'],
                    ['label' => 'Sampling Points', 'status' => 'pending'],
                    ['label' => 'Soil Readings', 'status' => 'pending'],
                    ['label' => 'Reports', 'status' => 'pending'],
                ];
            @endphp
            @foreach ($steps as $step)
                <div class="progress-step">
                    <div class="step-circle {{ $step['status'] }}">
                        @if ($step['status'] === 'complete')
                            <i class="fa-solid fa-check"></i>
                        @elseif ($step['status'] === 'in-progress')
                            <i class="fa-solid fa-ellipsis"></i>
                        @endif
                    </div>
                    <div class="step-label">{{ $step['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- C. LEAFLET MAP BANNER --}}
    <div class="map-card">
        <div class="map-card-header">
            <h3><i class="fa-solid fa-map-location-dot" style="color:#2D6A4F; margin-right:8px;"></i>Project Map</h3>
        </div>
        <div class="map-wrapper">
            <div id="overview-map"></div>
            <div class="map-overlay">
                <div class="map-overlay-box">
                    <i class="fa-solid fa-draw-polygon"></i>
                    <p>No Area of Interest defined yet.<br>Go to <strong>Area of Interest</strong> to draw your survey boundary.</p>
                    <a href="#"><i class="fa-solid fa-plus"></i> Define Area of Interest</a>
                </div>
            </div>
        </div>
    </div>

    {{-- D. KPI CARDS --}}
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-icon aoi"><i class="fa-solid fa-draw-polygon"></i></div>
            <div>
                <div class="kpi-value">0</div>
                <div class="kpi-label">Total AOIs</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon drone"><i class="fa-solid fa-helicopter"></i></div>
            <div>
                <div class="kpi-value">{{ $droneMissionCount ?? 0 }}</div>
                <div class="kpi-label">Drone Missions</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon sampling"><i class="fa-solid fa-map-pin"></i></div>
            <div>
                <div class="kpi-value">{{ $samplingPointCount ?? 0 }}</div>
                <div class="kpi-label">Sampling Points</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon soil"><i class="fa-solid fa-flask"></i></div>
            <div>
                <div class="kpi-value">{{ $soilReadingCount ?? 0 }}</div>
                <div class="kpi-label">Soil Readings</div>
            </div>
        </div>
    </div>

    {{-- E. RECENT ACTIVITY --}}
    <div class="activity-card">
        <h3>Recent Activity</h3>
        <ul class="activity-list">
            <li class="activity-item">
                <div class="activity-icon"><i class="fa-solid fa-folder-plus"></i></div>
                <div>
                    <div class="activity-text">Project "{{ $project->name ?? 'Pujada Bay Mangrove Rehabilitation' }}" was created.</div>
                    <div class="activity-time">July 1, 2026 &middot; 9:14 AM</div>
                </div>
            </li>
            <li class="activity-item">
                <div class="activity-icon"><i class="fa-solid fa-user-plus"></i></div>
                <div>
                    <div class="activity-text">Maria Santos was added as Field Surveyor.</div>
                    <div class="activity-time">July 2, 2026 &middot; 2:47 PM</div>
                </div>
            </li>
            <li class="activity-item">
                <div class="activity-icon"><i class="fa-solid fa-gear"></i></div>
                <div>
                    <div class="activity-text">Project settings were updated.</div>
                    <div class="activity-time">July 4, 2026 &middot; 11:02 AM</div>
                </div>
            </li>
        </ul>
    </div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var davaoOccidentalBounds = L.latLngBounds([5.15, 125.15], [6.95, 126.2]);
        var map = L.map('overview-map', {
            zoomControl: true,
            scrollWheelZoom: false,
            minZoom: 9,
            maxBounds: davaoOccidentalBounds,
            maxBoundsViscosity: 0.9,
            maxZoom: 17
        }).setView([6.4150, 125.6117], 10);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 17,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Re-check map size in case sidebar toggle affects layout
        setTimeout(function () {
            map.invalidateSize();
        }, 300);

        document.getElementById('sidebarToggle')?.addEventListener('click', function () {
            setTimeout(function () { map.invalidateSize(); }, 260);
        });
    });
</script>
@endsection
