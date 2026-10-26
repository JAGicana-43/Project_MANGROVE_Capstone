@extends('layouts.workspace')

@section('title', 'Survey Timeline')

@section('content')
<div class="section-shell">
    <div class="section-header">
        <div>
            <h1><i class="fa-solid fa-chart-line"></i> Survey Timeline</h1>
            <p>{{ $aoi->name ?: 'Unnamed area' }} — suitability trend across {{ $orthomosaicCount }} classified {{ Str::plural('survey', $orthomosaicCount) }}.</p>
        </div>
        <a href="{{ route('projects.comparisons.index', $project) }}" class="secondary-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to Comparisons
        </a>
    </div>

    @if ($orthomosaicCount < 3)
        <div class="section-card">
            <div class="empty-state">
                <i class="fa-solid fa-chart-line"></i>
                <p>A timeline needs at least three classified orthomosaics of this area so there's a real sequence to trend across. This area has {{ $orthomosaicCount }} so far — upload and classify another orthomosaic here to unlock it. Two surveys is what the regular Compare Surveys page already shows.</p>
            </div>
        </div>
    @else
        <div class="section-card">
            <h2 class="section-card-title"><i class="fa-solid fa-chart-line"></i> Average Suitability Over Time</h2>
            <div class="timeline-chart-wrap">
                <canvas id="timelineSuitabilityChart"></canvas>
            </div>
        </div>

        <div class="section-card">
            <h2 class="section-card-title"><i class="fa-solid fa-chart-column"></i> Cell Transitions Per Step</h2>
            <div class="timeline-chart-wrap">
                <canvas id="timelineTransitionsChart"></canvas>
            </div>
        </div>

        <div class="section-card">
            <h2 class="section-card-title"><i class="fa-solid fa-table-list"></i> Steps</h2>
            <div class="table-scroll">
                <table class="comparison-table">
                    <thead>
                        <tr><th>Through</th><th>Avg. Suitability</th><th>Improved</th><th>Declined</th><th>Unchanged</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($points as $point)
                            <tr>
                                <td>{{ $point['date'] }}</td>
                                <td>{{ $point['avg_suitability_after'] ?? '—' }}</td>
                                <td>{{ $point['improved'] }}</td>
                                <td>{{ $point['declined'] }}</td>
                                <td>{{ $point['unchanged'] }}</td>
                                <td><a href="{{ route('projects.comparisons.show', [$project, $point['comparison_id']]) }}">View &rarr;</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<style>
    .section-shell { display: flex; flex-direction: column; gap: 20px; }
    .section-header { background: #fff; border-radius: 16px; padding: 24px 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
    .section-header h1 { font-size: 24px; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    .section-header p { color: #6c757d; }
    .secondary-btn { display: inline-flex; align-items: center; gap: 8px; border: none; padding: 9px 14px; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; background: #f1f3f5; color: #495057; }
    .section-card { background: #fff; border-radius: 16px; padding: 24px 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section-card-title { font-size: 16px; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .empty-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 40px 20px; text-align: center; color: #6c757d; }
    .empty-state i { font-size: 28px; color: #ADB5BD; }
    .timeline-chart-wrap { height: 280px; }
    .table-scroll { overflow-x: auto; }
    .comparison-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .comparison-table th, .comparison-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e9ecef; }
    .comparison-table th { color: #6c757d; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: .03em; }
    .comparison-table a { color: #2D6A4F; font-weight: 600; text-decoration: none; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha512-k72Y+sws0148dhTBT8Nyro04RwjANbBxNCZ80xKT3FdHNON55/us15Ju0YQ2dD0uzyBJ5YjXnbUcLWaCaw3rCg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var points = @json($points);
        if (!points.length || typeof Chart === 'undefined') return;

        var labels = points.map(function (p) { return p.date; });

        var suitabilityCanvas = document.getElementById('timelineSuitabilityChart');
        if (suitabilityCanvas) {
            new Chart(suitabilityCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Avg. suitability score',
                        data: points.map(function (p) { return p.avg_suitability_after; }),
                        borderColor: '#2D6A4F',
                        backgroundColor: 'rgba(45, 106, 79, 0.12)',
                        fill: true,
                        tension: 0.25,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { min: 0, max: 1 } }
                }
            });
        }

        var transitionsCanvas = document.getElementById('timelineTransitionsChart');
        if (transitionsCanvas) {
            new Chart(transitionsCanvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Improved', data: points.map(function (p) { return p.improved; }), backgroundColor: '#2D6A4F' },
                        { label: 'Declined', data: points.map(function (p) { return p.declined; }), backgroundColor: '#B42318' },
                        { label: 'Unchanged', data: points.map(function (p) { return p.unchanged; }), backgroundColor: '#ADB5BD' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    });
</script>
@endsection
