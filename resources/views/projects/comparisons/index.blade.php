@extends('layouts.workspace')

@section('title', 'Compare Surveys')

@section('content')
<div class="section-shell">
    <div class="section-header">
        <div>
            <h1><i class="fa-solid fa-code-compare"></i> Compare Surveys</h1>
            <p>Compare two orthomosaics of the same area, flown at different times, to see how land cover and suitability changed.</p>
        </div>
    </div>

    <div class="section-card">
        @if ($comparableOrthomosaicsByAoi->isEmpty())
            <div class="empty-state">
                <i class="fa-solid fa-code-compare"></i>
                <p>Comparisons need at least two classified orthomosaics for the same area of interest. Upload and classify another orthomosaic for an existing area to unlock this.</p>
            </div>
        @else
            <form method="POST" action="{{ route('projects.comparisons.store', $project) }}" class="comparison-form">
                @csrf
                <div class="comparison-form-row">
                    <label>
                        <span>Area of Interest</span>
                        <select name="aoi_id" id="comparisonAoiSelect">
                            @foreach ($comparableOrthomosaicsByAoi as $aoiId => $orthomosaics)
                                @php $aoi = $aois->firstWhere('id', $aoiId); @endphp
                                <option value="{{ $aoiId }}">{{ $aoi->name ?? 'Unnamed area' }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @foreach ($comparableOrthomosaicsByAoi as $aoiId => $orthomosaics)
                    <div class="comparison-orthomosaic-group" data-aoi-id="{{ $aoiId }}" @if(!$loop->first) hidden @endif>
                        <div class="comparison-form-row">
                            <label>
                                <span>Earlier orthomosaic</span>
                                <select name="orthomosaic_a_id">
                                    @foreach ($orthomosaics as $orthomosaic)
                                        <option value="{{ $orthomosaic->id }}" @selected($loop->index === $orthomosaics->count() - 1)>
                                            {{ $orthomosaic->uploaded_at?->format('M d, Y g:i A') ?? 'Orthomosaic #' . $loop->iteration }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Later orthomosaic</span>
                                <select name="orthomosaic_b_id">
                                    @foreach ($orthomosaics as $orthomosaic)
                                        <option value="{{ $orthomosaic->id }}" @selected($loop->first)>
                                            {{ $orthomosaic->uploaded_at?->format('M d, Y g:i A') ?? 'Orthomosaic #' . $loop->iteration }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="action-btn">
                    <i class="fa-solid fa-code-compare"></i> Compare
                </button>
            </form>
        @endif
    </div>

    <div class="section-card">
        <div class="section-summary">
            <div class="summary-pill">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>{{ $comparisons->count() }} Past {{ Str::plural('Comparison', $comparisons->count()) }}</span>
            </div>
        </div>

        @if ($comparisons->isEmpty())
            <div class="empty-state">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <p>No comparisons have been generated yet.</p>
            </div>
        @else
            <div class="items-grid">
                @foreach ($comparisons as $comparison)
                    <a href="{{ route('projects.comparisons.show', [$project, $comparison]) }}" class="item-card comparison-item-card">
                        <div class="item-title-row">
                            <h3>{{ $comparison->areaOfInterest->name ?? 'Unnamed area' }}</h3>
                            <span class="status-pill">{{ $comparison->change_summary['transition_counts']['improved'] ?? 0 }} improved</span>
                        </div>
                        <div class="item-meta">
                            <span><i class="fa-regular fa-calendar"></i> {{ $comparison->orthomosaicA?->uploaded_at?->format('M d, Y') ?? '—' }} → {{ $comparison->orthomosaicB?->uploaded_at?->format('M d, Y') ?? '—' }}</span>
                            <span><i class="fa-solid fa-user"></i> {{ $comparison->generatedBy?->name ?? 'Unknown' }}</span>
                        </div>
                    </a>
                @endforeach
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
    .action-btn { display: inline-flex; align-items: center; gap: 8px; border: none; padding: 9px 14px; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; background: #2D6A4F; color: #fff; }
    .empty-state { display: flex; flex-direction: column; align-items: center; gap: 10px; color: #6c757d; padding: 24px 0; text-align: center; }
    .empty-state i { font-size: 28px; color: #74C69D; }
    .comparison-form { display: flex; flex-direction: column; gap: 16px; }
    .comparison-form-row { display: flex; gap: 16px; flex-wrap: wrap; }
    .comparison-form-row label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #495057; flex: 1; min-width: 220px; }
    .comparison-form-row select { padding: 9px 12px; border-radius: 10px; border: 1px solid #dee2e6; font-size: 13px; }
    .items-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .item-card { border: 1px solid #e9ecef; border-radius: 12px; padding: 16px; background: #fafafa; }
    .comparison-item-card { display: block; color: inherit; text-decoration: none; transition: border-color .15s ease; }
    .comparison-item-card:hover { border-color: #2D6A4F; }
    .item-title-row { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 10px; }
    .item-title-row h3 { font-size: 15px; color: #212529; }
    .status-pill { background: rgba(45,106,79,0.12); color: #2D6A4F; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
    .item-meta { display: flex; flex-direction: column; gap: 6px; color: #6c757d; font-size: 13px; }
    .item-meta i { margin-right: 6px; color: #74C69D; }
    @media (max-width: 768px) { .items-grid { grid-template-columns: 1fr; } .comparison-form-row { flex-direction: column; } }
</style>

<script>
    (function () {
        var aoiSelect = document.getElementById('comparisonAoiSelect');
        if (!aoiSelect) return;

        function syncGroups() {
            document.querySelectorAll('.comparison-orthomosaic-group').forEach(function (group) {
                var isActive = group.dataset.aoiId === aoiSelect.value;
                group.hidden = !isActive;
                // Only the visible group's selects should actually submit --
                // otherwise every hidden group's <select name="orthomosaic_a_id">
                // still posts a value and PHP silently keeps the last one in
                // document order, which may not be the AOI the user picked.
                group.querySelectorAll('select').forEach(function (select) {
                    select.disabled = !isActive;
                });
            });
        }

        aoiSelect.addEventListener('change', syncGroups);
        syncGroups();
    })();
</script>
@endsection
