<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function store(Request $request)
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'first_name'  => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'email'       => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'        => ['required', Rule::in(['admin', 'researcher', 'field_technician'])],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
            'avatar'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $fullName = collect([
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
        ])->filter(fn ($part) => filled($part))->implode(' ');

        $role = Role::where('name', $validated['role'])->firstOrFail();

        $user = User::create([
            'role_id'     => $role->id,
            'name'        => $fullName,
            'avatar_path' => $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null,
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'must_change_password' => true,
            'is_active'   => true,
            'status'      => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        AuditLog::record('created', 'users', $user->id, ['event' => 'user_created_by_admin']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $user->name . ' has been created.',
                'user'    => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'email'      => $user->email,
                    'role'       => $role->name,
                    'status'     => $user->status,
                    'is_active'  => $user->is_active,
                    'created_at' => $user->created_at->format('M d, Y'),
                ],
            ], 201);
        }

        return back()->with('success', $user->name . ' has been created.');
    }

    public function index(Request $request)
    {
        $currentUser = Auth::user();

        $users = User::with(['role', 'approvedBy'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->get();

        $stats = [
            'total'    => $users->count(),
            'pending'  => $users->where('status', 'pending')->count(),
            'approved' => $users->where('status', 'approved')->count(),
            'rejected' => $users->where('status', 'rejected')->count(),
            'active'   => $users->where('is_active', true)->count(),
        ];

        $adminNavRoute = 'users';
        $adminPendingCount = $stats['pending'];

        return view('admin.users', compact('currentUser', 'users', 'stats', 'adminNavRoute', 'adminPendingCount'));
    }

    public function update(Request $request, User $user)
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'avatar'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $user->name = $validated['name'];

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        $user->save();

        AuditLog::record('updated', 'users', $user->id, ['event' => 'user_updated_by_admin', 'by' => $admin->id]);

        return response()->json([
            'message' => $user->name . ' has been updated.',
            'user'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function show(User $user)
    {
        $user->load(['role', 'approvedBy', 'projectMemberships.project']);

        $recentActivity = AuditLog::where('user_id', $user->id)
            ->orWhere(function ($query) use ($user) {
                $query->where('table_name', 'users')->where('record_id', $user->id);
            })
            ->orderByDesc('logged_at')
            ->take(10)
            ->get(['action', 'table_name', 'payload', 'logged_at']);

        return response()->json([
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'avatar_url'    => $user->avatar_url,
            'role'          => optional($user->role)->name,
            'status'        => $user->status,
            'is_active'     => $user->is_active,
            'created_at'    => $user->created_at?->format('M d, Y g:i A'),
            'last_login_at' => $user->last_login_at?->format('M d, Y g:i A') ?? 'Never',
            'approved_by'   => optional($user->approvedBy)->name,
            'approved_at'   => $user->approved_at?->format('M d, Y g:i A'),
            'projects'      => $user->projectMemberships->map(fn ($membership) => [
                'name' => optional($membership->project)->name,
                'role' => $membership->role,
            ])->filter(fn ($project) => $project['name'] !== null)->values(),
            'activity' => $recentActivity->map(fn ($log) => [
                'action'    => $log->action,
                'note'      => $log->payload['event'] ?? null,
                'logged_at' => $log->logged_at?->format('M d, Y g:i A'),
            ]),
        ]);
    }

    public function approve(Request $request, User $user)
    {
        $admin = Auth::user();

        $user->update([
            'status'      => 'approved',
            'is_active'   => true,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        AuditLog::record('updated', 'users', $user->id, ['event' => 'user_approved']);

        return $this->respond($request, $user, $user->name . ' has been approved and can now log in.');
    }

    public function reject(Request $request, User $user)
    {
        $admin = Auth::user();

        abort_if($user->id === $admin->id, 403, 'You cannot reject your own account.');

        $user->update([
            'status'      => 'rejected',
            'is_active'   => false,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        AuditLog::record('updated', 'users', $user->id, ['event' => 'user_rejected']);

        return $this->respond($request, $user, $user->name . '\'s registration has been rejected.');
    }

    public function activate(Request $request, User $user)
    {
        $user->update(['is_active' => true]);

        AuditLog::record('updated', 'users', $user->id, ['event' => 'user_activated']);

        return $this->respond($request, $user, $user->name . ' has been activated.');
    }

    public function deactivate(Request $request, User $user)
    {
        $admin = Auth::user();

        abort_if($user->id === $admin->id, 403, 'You cannot deactivate your own account.');

        $user->update(['is_active' => false]);

        AuditLog::record('updated', 'users', $user->id, ['event' => 'user_deactivated']);

        return $this->respond($request, $user, $user->name . ' has been deactivated.');
    }

    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $admin = Auth::user();
        $ids = collect($validated['user_ids'])->reject(fn ($id) => $id === $admin->id)->values();

        $users = User::whereIn('id', $ids)->get();

        foreach ($users as $user) {
            $user->update([
                'status'      => 'approved',
                'is_active'   => true,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            AuditLog::record('updated', 'users', $user->id, ['event' => 'user_approved', 'bulk' => true]);
        }

        return response()->json([
            'message' => $users->count() . ' account(s) approved.',
            'count'   => $users->count(),
        ]);
    }

    public function bulkReject(Request $request)
    {
        $validated = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $admin = Auth::user();
        $ids = collect($validated['user_ids'])->reject(fn ($id) => $id === $admin->id)->values();

        $users = User::whereIn('id', $ids)->get();

        foreach ($users as $user) {
            $user->update([
                'status'      => 'rejected',
                'is_active'   => false,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            AuditLog::record('updated', 'users', $user->id, ['event' => 'user_rejected', 'bulk' => true]);
        }

        return response()->json([
            'message' => $users->count() . ' account(s) rejected.',
            'count'   => $users->count(),
        ]);
    }

    private function respond(Request $request, User $user, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message'   => $message,
                'user'      => [
                    'id'        => $user->id,
                    'status'    => $user->status,
                    'is_active' => $user->is_active,
                ],
            ]);
        }

        return back()->with('success', $message);
    }
}
