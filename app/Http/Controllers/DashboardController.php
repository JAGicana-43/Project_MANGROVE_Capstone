<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function admin()
    {
        $currentUser = Auth::user();

        $users = User::with('role')->get();

        $stats = [
            'total'            => $users->count(),
            'pending'          => $users->where('status', 'pending')->count(),
            'active'           => $users->where('is_active', true)->count(),
            'researchers'      => $users->filter(fn ($u) => optional($u->role)->name === 'researcher')->count(),
            'field_technicians' => $users->filter(fn ($u) => optional($u->role)->name === 'field_technician')->count(),
        ];

        $pendingUsers = $users->where('status', 'pending')->sortByDesc('created_at')->take(5)->values();

        $recentActivity = AuditLog::with('user')->orderByDesc('logged_at')->take(6)->get();

        $adminNavRoute = 'dashboard';
        $adminPendingCount = $stats['pending'];

        return view('dashboard.admin', compact('currentUser', 'stats', 'pendingUsers', 'recentActivity', 'adminNavRoute', 'adminPendingCount'));
    }

    public function lobby()
    {
        $user = Auth::user()->load('role');

        $projects = Project::query()
            ->withCount([
                'areasOfInterest as aoi_count',
                'members as member_count',
            ])
            // Loaded so the lobby's avatar stack can show the actual project
            // members (and their real uploaded photos), not a stand-in built
            // from the creator's name.
            ->with(['members' => function ($query) {
                $query->with('user')->orderBy('assigned_at');
            }])
            ->where('created_by', $user->id)
            ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();

        return view('dashboard.lobby', compact('user', 'projects'));
    }

    public function fieldtech()
    {
        $user = Auth::user();
        return view('dashboard.fieldtech', compact('user'));
    }
}
