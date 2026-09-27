<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ResearcherUserController extends Controller
{
    public function store(Request $request)
    {
        $researcher = Auth::user();

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $role = Role::where('name', 'field_technician')->firstOrFail();

        $user = User::create([
            'role_id'     => $role->id,
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'is_active'   => true,
            'status'      => 'approved',
            'approved_by' => $researcher->id,
            'approved_at' => now(),
        ]);

        AuditLog::record('created', 'users', $user->id, ['event' => 'field_technician_created_by_researcher']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $user->name . ' has been added as a field researcher.',
                'user'    => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
            ], 201);
        }

        return back()->with('success', $user->name . ' has been added as a field researcher.');
    }
}
