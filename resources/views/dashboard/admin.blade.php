@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="page-header">
    <div>
        <h1>Welcome back, {{ $currentUser->name }}</h1>
        <p>Here's what's happening across Project MANGROVE right now.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="glass-panel stat-tile">
        <span class="stat-tile-icon"><i class="fa-solid fa-users"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $stats['total'] }}">0</div>
            <div class="stat-tile-label">Total Accounts</div>
        </div>
    </div>
    <div class="glass-panel stat-tile {{ $stats['pending'] > 0 ? 'warn' : '' }}">
        <span class="stat-tile-icon"><i class="fa-solid fa-hourglass-half"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $stats['pending'] }}">0</div>
            <div class="stat-tile-label">Pending Approval</div>
        </div>
    </div>
    <div class="glass-panel stat-tile">
        <span class="stat-tile-icon"><i class="fa-solid fa-user-check"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $stats['active'] }}">0</div>
            <div class="stat-tile-label">Active Accounts</div>
        </div>
    </div>
    <div class="glass-panel stat-tile">
        <span class="stat-tile-icon"><i class="fa-solid fa-flask"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $stats['researchers'] }}">0</div>
            <div class="stat-tile-label">Researchers</div>
        </div>
    </div>
    <div class="glass-panel stat-tile">
        <span class="stat-tile-icon"><i class="fa-solid fa-person-digging"></i></span>
        <div>
            <div class="stat-tile-value" data-count="{{ $stats['field_technicians'] }}">0</div>
            <div class="stat-tile-label">Field Technicians</div>
        </div>
    </div>
</div>

<div class="dash-columns" x-data="pendingWidget()">
    <section class="glass-panel dash-card">
        <div class="dash-card-header">
            <h2><i class="fa-solid fa-hourglass-half"></i> Pending Approvals</h2>
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">View all <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        @if($pendingUsers->isEmpty())
            <div class="dash-empty"><i class="fa-solid fa-circle-check"></i> No accounts waiting for review.</div>
        @else
            <ul class="pending-list">
                @foreach($pendingUsers as $pendingUser)
                    <li class="pending-row" x-show="!removed.includes('{{ $pendingUser->id }}')" x-cloak>
                        <div>
                            <div class="pending-name">{{ $pendingUser->name }}</div>
                            <div class="pending-meta">{{ $pendingUser->email }} &middot; {{ ucfirst(str_replace('_',' ', optional($pendingUser->role)->name)) }}</div>
                        </div>
                        <div class="pending-actions">
                            <button type="button" class="btn btn-primary btn-sm" @click="act('{{ $pendingUser->id }}', 'approve', '{{ addslashes($pendingUser->name) }}')">
                                <i class="fa-solid fa-check"></i> Approve
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" @click="act('{{ $pendingUser->id }}', 'reject', '{{ addslashes($pendingUser->name) }}')">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="glass-panel dash-card">
        <div class="dash-card-header">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</h2>
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-ghost btn-sm">View all <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        @if($recentActivity->isEmpty())
            <div class="dash-empty"><i class="fa-solid fa-circle-info"></i> No activity recorded yet.</div>
        @else
            <ul class="activity-list">
                @foreach($recentActivity as $log)
                    <li class="activity-row">
                        <span class="activity-icon"><i class="fa-solid fa-{{ match(true) {
                            str_contains($log->action, 'login') => 'right-to-bracket',
                            str_contains($log->action, 'logout') => 'right-from-bracket',
                            $log->action === 'created' => 'plus',
                            default => 'pen',
                        } }}"></i></span>
                        <div>
                            <div class="activity-text">
                                <strong>{{ $log->user->name ?? 'System' }}</strong>
                                {{ str_replace('_', ' ', $log->payload['event'] ?? $log->action) }}
                            </div>
                            <div class="activity-time">{{ $log->logged_at?->diffForHumans() }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection

@section('scripts')
<style>
    .dash-columns { display: grid; grid-template-columns: 1.3fr 1fr; gap: 16px; }
    @media (max-width: 900px) { .dash-columns { grid-template-columns: 1fr; } }

    .dash-card { padding: 18px 20px; }
    .dash-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .dash-card-header h2 { font-size: 15px; display: flex; align-items: center; gap: 8px; }
    .dash-card-header h2 i { color: var(--accent-green); font-size: 14px; }

    .dash-empty { padding: 22px 4px; text-align: center; color: rgba(var(--ink-rgb),0.5); font-size: 13.5px; font-weight: 600; }
    .dash-empty i { margin-right: 6px; color: var(--sage-green); }

    .pending-list, .activity-list { list-style: none; display: flex; flex-direction: column; gap: 4px; }

    .pending-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 6px;
        border-bottom: 1px solid var(--border-soft);
        transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .pending-row:last-child { border-bottom: none; }
    .pending-name { font-weight: 700; font-size: 13.5px; }
    .pending-meta { font-size: 12px; color: rgba(var(--ink-rgb),0.55); margin-top: 1px; }
    .pending-actions { display: flex; gap: 6px; flex: 0 0 auto; }

    .activity-row { display: flex; align-items: flex-start; gap: 10px; padding: 9px 6px; border-bottom: 1px solid var(--border-soft); }
    .activity-row:last-child { border-bottom: none; }
    .activity-icon {
        width: 30px; height: 30px; border-radius: 9px;
        display: inline-flex; align-items: center; justify-content: center;
        background: rgba(45,106,79,0.10); color: var(--accent-green); font-size: 12px; flex: 0 0 auto;
    }
    .activity-text { font-size: 13px; text-transform: capitalize; }
    .activity-text strong { text-transform: none; }
    .activity-time { font-size: 11.5px; color: rgba(var(--ink-rgb),0.5); margin-top: 1px; text-transform: none; }
</style>
<script>
    function pendingWidget() {
        return {
            removed: [],
            act(userId, action, name) {
                fetch('/admin/users/' + userId + '/' + action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.adminCsrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
                    .then((result) => {
                        if (!result.ok) throw new Error(result.data.message || 'Action failed.');
                        this.removed.push(userId);
                        window.adminToast(result.data.message || (name + ' updated.'), 'success');
                    })
                    .catch((error) => {
                        window.adminToast(error.message || 'Something went wrong.', 'error');
                    });
            }
        };
    }
</script>
@endsection
