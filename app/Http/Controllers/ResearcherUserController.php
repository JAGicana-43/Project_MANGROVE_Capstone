<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResearcherUserController extends Controller
{
    public function store(Request $request)
    {
        $researcher = Auth::user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $role = Role::where('name', 'field_technician')->firstOrFail();

        // No password field here on purpose -- the researcher creating this
        // account should never know its real password (that's effectively
        // a shared/known credential). The account gets an unusable random
        // hash instead, and Password::sendResetLink() below re-purposes the
        // same "set your password" email/link flow ForgotPasswordController
        // already uses, so the field researcher is the only one who ever
        // sets (and knows) the real password.
        $user = User::create([
            'role_id'     => $role->id,
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make(Str::random(40)),
            'is_active'   => true,
            'status'      => 'approved',
            'approved_by' => $researcher->id,
            'approved_at' => now(),
        ]);

        Password::sendResetLink(['email' => $user->email]);

        AuditLog::record('created', 'users', $user->id, ['event' => 'field_technician_invited_by_researcher']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $user->name . ' has been added. An email has been sent to ' . $user->email . ' so they can set their own password.',
                'user'    => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
            ], 201);
        }

        return back()->with('success', $user->name . ' has been added. An email has been sent to ' . $user->email . ' so they can set their own password.');
    }
}
