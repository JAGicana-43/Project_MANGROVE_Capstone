@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('content')
<div class="page-header">
    <div>
        <h1>Audit Logs</h1>
        <p>Full trail of account and system actions across Project MANGROVE.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="glass-panel stat-tile">
        <span class="stat-tile-icon"><i class="fa-solid fa-bolt"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $activityStats['actions_today'] }}">0</div>
            <div class="stat-tile-label">Actions Today</div>
        </div>
    </div>
    <div class="glass-panel stat-tile">
        <span class="stat-tile-icon"><i class="fa-solid fa-right-to-bracket"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $activityStats['logins_today'] }}">0</div>
            <div class="stat-tile-label">Successful Logins Today</div>
        </div>
    </div>
    <div class="glass-panel stat-tile {{ $activityStats['failed_logins_week'] > 0 ? 'warn' : '' }}">
        <span class="stat-tile-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $activityStats['failed_logins_week'] }}">0</div>
            <div class="stat-tile-label">Failed Logins (7d)</div>
        </div>
    </div>
    <div class="glass-panel stat-tile">
        <span class="stat-tile-icon"><i class="fa-solid fa-user-clock"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $activityStats['active_users_week'] }}">0</div>
            <div class="stat-tile-label">Active Users (7d)</div>
        </div>
    </div>
</div>

<div class="glass-panel chart-card">
    <div class="chart-card-header">
        <h2><i class="fa-solid fa-chart-column"></i> Activity — last 14 days</h2>
        <span class="chart-total">{{ number_format($activityChart->sum('count')) }} actions logged</span>
    </div>
    @php
        $chartMax = max(1, $activityChart->max('count'));
        $chartMidValue = (int) ceil($chartMax / 2);
    @endphp
    <div class="activity-chart">
        <div class="activity-chart-axis">
            <span>{{ number_format($chartMax) }}</span>
            <span>{{ number_format($chartMidValue) }}</span>
            <span>0</span>
        </div>
        <div class="activity-chart-plot" role="img" aria-label="Bar chart of audit log activity for the last 14 days">
            <div class="activity-chart-grid" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
            <div class="activity-chart-bars">
                @foreach($activityChart as $index => $day)
                    <div class="activity-bar-col">
                        <button
                            type="button"
                            class="activity-bar"
                            style="height: {{ $day['count'] > 0 ? max(3, round($day['count'] / $chartMax * 100)) : 0 }}%"
                            data-label="{{ $day['full_label'] }}"
                            data-value="{{ $day['count'] }}"
                            aria-label="{{ $day['full_label'] }}: {{ $day['count'] }} {{ Str::plural('action', $day['count']) }}"
                        ></button>
                        <span class="activity-bar-tick">{{ $index % 2 === 0 ? $day['label'] : '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="activity-tooltip" id="activityTooltip" hidden role="status" aria-live="polite">
        <strong id="activityTooltipValue"></strong>
        <span id="activityTooltipLabel"></span>
    </div>
</div>

<form method="GET" action="{{ route('admin.audit-logs.index') }}" class="glass-panel toolbar-card" data-loading-label="Filtering logs...">
    <select name="user_id">
        <option value="">All users</option>
        @foreach($users as $u)
            <option value="{{ $u->id }}" {{ request('user_id') === $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
        @endforeach
    </select>
    <select name="action">
        <option value="">All actions</option>
        @foreach($actions as $action)
            <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ ucfirst($action) }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" title="From date">
    <input type="date" name="to" value="{{ request('to') }}" title="To date">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
    @if(request()->hasAny(['user_id', 'action', 'from', 'to']))
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-ghost btn-sm">Reset</a>
    @endif
    <span class="result-count">{{ $logs->total() }} entries</span>
</form>

<div class="glass-panel table-card">
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Table</th>
                <th>Details</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->logged_at?->format('M d, Y g:i A') }}</td>
                    <td>{{ $log->user->name ?? 'System' }}</td>
                    <td><span class="badge badge-role">{{ $log->action }}</span></td>
                    <td>{{ $log->table_name ?? '—' }}</td>
                    <td>{{ $log->payload['event'] ?? '—' }}</td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-note">No audit log entries match these filters.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($logs->lastPage() > 1)
    <div class="pager">
        <a href="{{ $logs->previousPageUrl() }}" class="btn btn-ghost btn-sm {{ $logs->onFirstPage() ? 'disabled' : '' }}"><i class="fa-solid fa-chevron-left"></i> Prev</a>
        <span class="pager-info">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
        <a href="{{ $logs->nextPageUrl() }}" class="btn btn-ghost btn-sm {{ !$logs->hasMorePages() ? 'disabled' : '' }}">Next <i class="fa-solid fa-chevron-right"></i></a>
    </div>
@endif
@endsection

@section('scripts')
<style>
    .chart-card { padding: 18px 20px 8px; margin-bottom: 22px; }
    .chart-card-header { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .chart-card-header h2 { font-size: 15px; display: flex; align-items: center; gap: 8px; }
    .chart-card-header h2 i { color: var(--accent-green); font-size: 14px; }
    .chart-total { font-size: 12.5px; font-weight: 700; color: rgba(var(--ink-rgb),0.55); }

    .activity-chart { display: flex; gap: 12px; }
    .activity-chart-axis {
        display: flex; flex-direction: column; justify-content: space-between;
        height: 160px; padding-bottom: 22px;
        font-size: 11px; font-weight: 700; color: rgba(var(--ink-rgb),0.45);
        font-variant-numeric: tabular-nums;
    }
    .activity-chart-plot { position: relative; flex: 1; min-width: 0; }
    .activity-chart-grid {
        position: absolute; inset: 0 0 22px 0;
        display: flex; flex-direction: column; justify-content: space-between;
        pointer-events: none;
    }
    .activity-chart-grid span { display: block; height: 1px; background: var(--border-soft); }

    .activity-chart-bars {
        position: relative; z-index: 1;
        display: flex; align-items: flex-end; gap: 2px;
        height: 160px; padding-bottom: 22px;
    }
    .activity-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
    .activity-bar {
        width: 100%; max-width: 24px; min-height: 2px;
        border: none; border-radius: 4px 4px 0 0;
        background: var(--accent-green);
        cursor: pointer; padding: 0;
        transition: filter 0.12s ease, transform 0.12s ease;
    }
    .activity-bar:hover, .activity-bar:focus-visible {
        filter: brightness(1.12);
        outline: none;
    }
    .activity-bar-tick {
        margin-top: 6px; font-size: 10.5px; font-weight: 700;
        color: rgba(var(--ink-rgb),0.45); white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .activity-tooltip {
        position: fixed;
        z-index: 3200;
        display: flex; flex-direction: column; gap: 2px;
        padding: 8px 11px;
        border-radius: 10px;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        box-shadow: var(--panel-shadow);
        font-size: 12px;
        pointer-events: none;
        transform: translate(-50%, -100%);
    }
    .activity-tooltip[hidden] { display: none; }
    .activity-tooltip strong { font-size: 14px; font-weight: 800; color: var(--dark-gray); }
    .activity-tooltip span { color: rgba(var(--ink-rgb),0.6); font-weight: 600; }

    .toolbar-card { display: flex; align-items: center; gap: 10px; padding: 12px 14px; margin-bottom: 14px; flex-wrap: wrap; }
    .toolbar-card select, .toolbar-card input[type="date"] {
        height: 40px; padding: 0 10px; border-radius: 10px; border: 1px solid var(--border-soft);
        background: rgba(var(--surface-rgb),0.7); font: inherit; font-size: 13px; font-weight: 600; color: var(--dark-gray);
    }
    .result-count { margin-left: auto; font-size: 12.5px; font-weight: 700; color: rgba(var(--ink-rgb),0.5); white-space: nowrap; }

    .table-card { padding: 0; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.04em;
        color: rgba(var(--ink-rgb),0.55); padding: 12px 14px; border-bottom: 1px solid var(--border-soft);
        background: rgba(var(--surface-rgb),0.5); white-space: nowrap;
    }
    tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border-soft); font-size: 13px; white-space: nowrap; }
    tbody tr:last-child td { border-bottom: none; }

    .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 800; text-transform: capitalize; }
    .badge-role { background: rgba(45,106,79,0.12); color: var(--accent-green); }

    .empty-note { padding: 40px; text-align: center; color: rgba(var(--ink-rgb),0.5); font-weight: 600; white-space: normal; }

    .pager { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 16px; }
    .pager-info { font-size: 12.5px; font-weight: 700; color: rgba(var(--ink-rgb),0.55); }
    .pager a.disabled { pointer-events: none; opacity: 0.4; }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltip = document.getElementById('activityTooltip');
        var tooltipValue = document.getElementById('activityTooltipValue');
        var tooltipLabel = document.getElementById('activityTooltipLabel');
        if (!tooltip) return;

        function showTooltip(bar) {
            var count = bar.dataset.value;
            tooltipValue.textContent = count + ' ' + (count === '1' ? 'action' : 'actions');
            tooltipLabel.textContent = bar.dataset.label;

            var rect = bar.getBoundingClientRect();
            tooltip.style.left = (rect.left + rect.width / 2) + 'px';
            tooltip.style.top = (rect.top - 8) + 'px';
            tooltip.hidden = false;
        }

        function hideTooltip() {
            tooltip.hidden = true;
        }

        document.querySelectorAll('.activity-bar').forEach(function (bar) {
            bar.addEventListener('pointerenter', function () { showTooltip(bar); });
            bar.addEventListener('pointerleave', hideTooltip);
            bar.addEventListener('focus', function () { showTooltip(bar); });
            bar.addEventListener('blur', hideTooltip);
        });
    });
</script>
@endsection
