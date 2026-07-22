<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('logged_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->string('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('from')) {
            $query->whereDate('logged_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('logged_at', '<=', $request->date('to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $users = User::orderBy('name')->get(['id', 'name']);

        $adminNavRoute = 'audit-logs';
        $adminPendingCount = User::where('status', 'pending')->count();

        $activityStats = [
            'actions_today'       => AuditLog::whereDate('logged_at', now()->toDateString())->count(),
            'logins_today'        => LoginAttempt::where('success', true)->whereDate('attempted_at', now()->toDateString())->count(),
            'failed_logins_week'  => LoginAttempt::where('success', false)->where('attempted_at', '>=', now()->subDays(7))->count(),
            'active_users_week'   => User::where('last_login_at', '>=', now()->subDays(7))->count(),
        ];

        $chartStart = now()->subDays(13)->startOfDay();
        $dailyCounts = AuditLog::selectRaw('DATE(logged_at) as day, COUNT(*) as total')
            ->where('logged_at', '>=', $chartStart)
            ->groupBy('day')
            ->pluck('total', 'day');

        $activityChart = collect(range(0, 13))->map(function ($i) use ($chartStart, $dailyCounts) {
            $date = $chartStart->copy()->addDays($i);
            $key = $date->format('Y-m-d');

            return [
                'label'      => $date->format('M j'),
                'full_label' => $date->format('M j, Y'),
                'count'      => (int) ($dailyCounts[$key] ?? 0),
            ];
        });

        return view('admin.audit-logs', compact('logs', 'actions', 'users', 'adminNavRoute', 'adminPendingCount', 'activityStats', 'activityChart'));
    }
}
