<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountSettingsController extends Controller
{
    public function edit()
    {
        $user = Auth::user()->load('role');

        $backRoute = match ($user->role->name ?? null) {
            'admin' => route('admin.dashboard'),
            'researcher' => route('researcher.lobby'),
            'field_technician' => route('fieldtech.dashboard'),
            default => route('login'),
        };

        return view('settings.account', compact('user', 'backRoute'));
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Your current password is incorrect.'])
                ->withInput();
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        // Matches the existing 'updated'/'users'/payload.event convention used
        // throughout AdminUserController (e.g. user_approved, user_activated)
        // — this one shows up automatically in the admin's per-user activity
        // feed since that view already queries table_name=users/record_id.
        AuditLog::record('updated', 'users', $user->id, ['event' => 'user_changed_password']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your password has been updated. Please log in again.');
    }
}
