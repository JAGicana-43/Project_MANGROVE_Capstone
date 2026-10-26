@extends('layouts.workspace')

@section('title', 'Survey Comparison')

@php
    $summary = $comparison->change_summary ?? [];
    $transitionCounts = $summary['transition_counts'] ?? ['improved' => 0, 'declined' => 0, 'unchanged' => 0];
    $coverBreakdown = $summary['cover_transition_breakdown'] ?? [];

    $surfaceA = $runA && $runA->status === 'completed' ? $runA->suitabilitySurfaces()->latest()->first() : null;
    $surfaceB = $runB && $runB->status === 'completed' ? $runB->suitabilitySurfaces()->latest()->first() : null;

    $mapConfigA = ($runA && $comparison->orthomosaicA?->bounds_coordinates) ? [
        'bounds' => $comparison->orthomosaicA->bounds_coordinates,
        'overlay_url' => $surfaceA ? route('classification.overlay', $runA->id) : null,
        'legend' => $surfaceA?->legend ?? [],
    ] : null;
    $mapConfigB = ($runB && $comparison->orthomosaicB?->bounds_coordinates) ? [
        'bounds' => $comparison->orthomosaicB->bounds_coordinates,
        'overlay_url' => $surfaceB ? route('classification.overlay', $runB->id) : null,
        'legend' => $surfaceB?->legend ?? [],
    ] : null;

    $coverLabels = ['bare_soil' => 'Bare Soil', 'vegetation' => 'Vegetation', 'open_water' => 'Open Water'];
    $changeCells = $summary['cells'] ?? [];
@endphp

@section('content')
<div class="section-shell">
    <div class="section-header">
        <div>
            <h1><i class="fa-solid fa-code-compare"></i> Survey Comparison</h1>
            <p>{{ $comparison->areaOfInterest->name ?? 'Unnamed area' }} — {{ $comparison->orthomosaicA?->uploaded_at?->format('M d, Y') ?? '—' }} → {{ $comparison->orthomosaicB?->uploaded_at?->format('M d, Y') ?? '—' }}</p>
        </div>
        <a href="{{ isset($project) && !empty($project->id) ? route('projects.comparisons.index', $project) : '#' }}" class="secondary-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to Comparisons
        </a>
    </div>

    <div class="comparison-summary-grid">
        <div class="comparison-summary-card">
            <span class="comparison-summary-value">{{ $summary['matched_cells'] ?? 0 }}</span>
            <span class="comparison-summary-label">Matched Cells</span>
        </div>
        <div class="comparison-summary-card improved">
            <span class="comparison-summary-value">{{ $transitionCounts['improved'] ?? 0 }}</span>
            <span class="comparison-summary-label">Improved</span>
        </div>
        <div class="comparison-summary-card declined">
            <span class="comparison-summary-value">{{ $transitionCounts['declined'] ?? 0 }}</span>
            <span class="comparison-summary-label">Declined</span>
        </div>
        <div class="comparison-summary-card">
            <span class="comparison-summary-value">{{ $transitionCounts['unchanged'] ?? 0 }}</span>
            <span class="comparison-summary-label">Unchanged</span>
        </div>
        <div class="comparison-summary-card">
            <span class="comparison-summary-value">
                {{ $summary['average_suitability_score_before'] ?? '—' }} → {{ $summary['average_suitability_score_after'] ?? '—' }}
            </span>
            <span class="comparison-summary-label">Avg. Suitability Score</span>
        </div>
    </div>

    <div class="section-card">
        <h2 class="section-card-title"><i class="fa-solid fa-table-list"></i> Land Cover Transitions</h2>
        @if (empty($coverBreakdown))
            <p class="muted">No matched cells to report.</p>
        @else
            <div class="table-scroll">
                <table class="comparison-table">
                    <thead>
                        <tr><th>Earlier Cover</th><th>Later Cover</th><th>Cells</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($coverBreakdown as $key => $count)
                            @php [$before, $after] = explode('_to_', $key); @endphp
                            <tr>
                                <td>{{ $coverLabels[$before] ?? $before }}</td>
                                <td>{{ $coverLabels[$after] ?? $after }}</td>
                                <td>{{ $count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if (($summary['unmatched_earlier_cells'] ?? 0) > 0 || ($summary['unmatched_later_cells'] ?? 0) > 0)
            <p class="muted comparison-unmatched-note">
                {{ $summary['unmatched_earlier_cells'] ?? 0 }} cell(s) from the earlier survey and
                {{ $summary['unmatched_later_cells'] ?? 0 }} cell(s) from the later survey had no close real-world
                match in the other survey (outside the {{ config('classification.comparison_match_max_distance_meters') }}m
                matching distance) and are excluded from the counts above.
            </p>
        @endif
    </div>

    <div class="comparison-maps-grid">
        <div class="section-card">
            <h2 class="section-card-title">Earlier — {{ $comparison->orthomosaicA?->uploaded_at?->format('M d, Y g:i A') ?? 'Unknown date' }}</h2>
            <div id="comparisonMapA" class="comparison-map"></div>
        </div>
        <div class="section-card">
            <h2 class="section-card-title">Later — {{ $comparison->orthomosaicB?->uploaded_at?->format('M d, Y g:i A') ?? 'Unknown date' }}</h2>
            <div id="comparisonMapB" class="comparison-map"></div>
        </div>
    </div>

    <div class="section-card">
        <h2 class="section-card-title"><i class="fa-solid fa-map-location-dot"></i> What Changed</h2>
        @if (empty($changeCells))
            <p class="muted">This comparison was generated before the change map existed. Re-run it (compare the same two orthomosaics again from the Compare Surveys page) to see it here.</p>
        @else
            <p class="muted" style="margin-bottom:12px;">Each cell shaded by how its land cover changed between the two surveys.</p>
            <div id="comparisonChangeMap" class="comparison-map comparison-change-map"></div>
            <ul class="comparison-change-legend">
                <li><span class="swatch improved"></span> Improved</li>
                <li><span class="swatch declined"></span> Declined</li>
                <li><span class="swatch unchanged"></span> Unchanged</li>
            </ul>
        @endif
    </div>
</div>

<style>
    .section-shell { display: flex; flex-direction: column; gap: 20px; }
    .section-header { background: #fff; border-radius: 16px; padding: 24px 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
    .section-header h1 { font-size: 24px; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    .section-header p { color: #6c757d; }
    .secondary-btn { display: inline-flex; align-items: center; gap: 8px; border: none; padding: 9px 14px; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; background: #f1f3f5; color: #495057; }
    .section-card { background: #fff; border-radius: 16px; padding: 24px 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section-card-title { font-size: 16px; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .muted { color: #6c757d; font-size: 13px; }
    .comparison-summary-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; }
    .comparison-summary-card { background: #fff; border-radius: 16px; padding: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 6px; }
    .comparison-summary-value { font-size: 24px; font-weight: 700; color: #212529; }
    .comparison-summary-label { font-size: 12px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
    .comparison-summary-card.improved .comparison-summary-value { color: #2D6A4F; }
    .comparison-summary-card.declined .comparison-summary-value { color: #B42318; }
    .table-scroll { overflow-x: auto; }
    .comparison-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .comparison-table th, .comparison-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e9ecef; }
    .comparison-table th { color: #6c757d; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: .03em; }
    .comparison-unmatched-note { margin-top: 12px; }
    .comparison-maps-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .comparison-map { height: 420px; border-radius: 12px; overflow: hidden; }
    .comparison-change-map { height: 480px; }
    .comparison-change-legend { display: flex; gap: 18px; margin-top: 12px; list-style: none; font-size: 12.5px; color: #495057; }
    .comparison-change-legend li { display: flex; align-items: center; gap: 7px; }
    .comparison-change-legend .swatch { width: 13px; height: 13px; border-radius: 3px; display: inline-block; }
    .comparison-change-legend .swatch.improved { background: #2D6A4F; }
    .comparison-change-legend .swatch.declined { background: #B42318; }
    .comparison-change-legend .swatch.unchanged { background: #ADB5BD; }
    @media (max-width: 1024px) { .comparison-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .comparison-maps-grid { grid-template-columns: 1fr; } }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var mapConfigA = @json($mapConfigA);
        var mapConfigB = @json($mapConfigB);

        function initCompassWidget(targetMap) {
            var Compass = L.Control.extend({
                options: { position: 'topright' },
                onAdd: function () {
                    var wrap = L.DomUtil.create('div', 'map-compass-widget');
                    wrap.innerHTML =
                        '<div class="map-compass-rose">'
                        + '<svg viewBox="0 0 64 64" width="48" height="48">'
                        + '<circle cx="32" cy="32" r="29" fill="#fff" stroke="#2D6A4F" stroke-width="2"/>'
                        + '<polygon points="32,7 38,32 32,29 26,32" fill="#B42318"/>'
                        + '<polygon points="32,57 38,32 32,35 26,32" fill="#9CA3AF"/>'
                        + '<text x="32" y="18" text-anchor="middle" font-size="9" font-weight="700" fill="#212529">N</text>'
                        + '</svg></div>';
                    L.DomEvent.disableClickPropagation(wrap);
                    return wrap;
                }
            });
            targetMap.addControl(new Compass());
        }

        function renderComparisonMap(elementId, config) {
            var el = document.getElementById(elementId);
            if (!el) return;

            var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxNativeZoom: 18,
                maxZoom: 20,
                attribution: 'Tiles &copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community'
            });

            var map = L.map(elementId, { zoomControl: false, layers: [satellite] });
            L.control.zoom({ position: 'bottomright' }).addTo(map);
            initCompassWidget(map);

            if (!config || !config.bounds || config.bounds.length !== 4) {
                map.setView([6.4150, 125.6117], 10);
                return;
            }

            var b = config.bounds;
            var bounds = [[b[0], b[1]], [b[2], b[3]]];
            map.fitBounds(bounds);

            if (config.overlay_url) {
                L.imageOverlay(config.overlay_url, bounds, { opacity: 0.65 }).addTo(map);
            }

            setTimeout(function () {
                map.invalidateSize();
                map.fitBounds(bounds);
            }, 150);
        }

        renderComparisonMap('comparisonMapA', mapConfigA);
        renderComparisonMap('comparisonMapB', mapConfigB);

        // "What Changed" map -- one rectangle per matched cell, colored by
        // transition. Independent of renderComparisonMap() above (no raster
        // overlay, just geometry), so it's its own small function rather
        // than bolting a third mode onto that one.
        var changeCells = @json($changeCells);
        var changeMapEl = document.getElementById('comparisonChangeMap');
        if (changeMapEl && changeCells.length) {
            var TRANSITION_COLOR = { improved: '#2D6A4F', declined: '#B42318', unchanged: '#ADB5BD' };

            var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxNativeZoom: 18,
                maxZoom: 20,
                attribution: 'Tiles &copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community'
            });
            var changeMap = L.map('comparisonChangeMap', { zoomControl: false, layers: [satellite] });
            L.control.zoom({ position: 'bottomright' }).addTo(changeMap);
            initCompassWidget(changeMap);

            var bounds = [];
            changeCells.forEach(function (cell) {
                if (!cell.bounds || cell.bounds.length !== 4) return;
                var rectBounds = [[cell.bounds[0], cell.bounds[1]], [cell.bounds[2], cell.bounds[3]]];
                var color = TRANSITION_COLOR[cell.transition] || TRANSITION_COLOR.unchanged;

                L.rectangle(rectBounds, {
                    color: color, weight: 1, fillColor: color, fillOpacity: 0.55
                }).addTo(changeMap).bindTooltip(
                    (cell.cover_before || '—') + ' &rarr; ' + (cell.cover_after || '—')
                );

                bounds.push(rectBounds[0], rectBounds[1]);
            });

            if (bounds.length) {
                changeMap.fitBounds(bounds);
                setTimeout(function () {
                    changeMap.invalidateSize();
                    changeMap.fitBounds(bounds);
                }, 150);
            } else {
                changeMap.setView([6.4150, 125.6117], 10);
            }
        }
    });
</script>
@endsection
