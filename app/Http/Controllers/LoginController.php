<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\LoginAttempt;
use App\Models\AuditLog;

class LoginController extends Controller
{
    // Maximum failed attempts before lockout
    const MAX_ATTEMPTS = 3;
    const LOCKOUT_MINUTES = 15;

    // Show the login page
    public function showLogin(Request $request)
    {
        // If already logged in, redirect to dashboard
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        $isFirstVisit = !$request->hasCookie('mangrove_visited');

        $response = response()->view('auth.login', ['isFirstVisit' => $isFirstVisit]);

        if ($isFirstVisit) {
            $response->cookie('mangrove_visited', '1', 60 * 24 * 365 * 5);
        }

        return $response;
    }

    // Handle login form submission
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->email;

        // Check if account is locked out
        $recentFails = LoginAttempt::recentFailedCount($email, self::LOCKOUT_MINUTES);
        if ($recentFails >= self::MAX_ATTEMPTS) {
            return back()->with(
                'locked',
                'Your account has been temporarily locked due to too many failed login attempts. Please try again after ' . self::LOCKOUT_MINUTES . ' minutes.'
            );
        }

        // Find the user
        $user = User::where('email', $email)->first();

        // Check credentials
        if (!$user || !Hash::check($request->password, $user->password)) {
            // Record failed attempt
            LoginAttempt::create([
                'user_id'      => $user?->id,
                'email'        => $email,
                'ip_address'   => $request->ip(),
                'success'      => false,
                'attempted_at' => now(),
            ]);

            $remaining = self::MAX_ATTEMPTS - ($recentFails + 1);
            $message = $remaining > 0
                ? "Invalid email or password. {$remaining} attempt(s) remaining before lockout."
                : 'Invalid email or password. Your account is now locked for ' . self::LOCKOUT_MINUTES . ' minutes.';

            return back()->with('error', $message)->withInput(['email' => $email]);
        }

        // Check if account is active / approved
        if (!$user->is_active) {
            $message = match ($user->status) {
                'pending'  => 'Your account is awaiting administrator approval. Please check back later.',
                'rejected' => 'Your account registration was not approved. Please contact the administrator.',
                default    => 'Your account has been deactivated. Please contact the administrator.',
            };

            return back()->with('error', $message);
        }

        // Successful login
        LoginAttempt::create([
            'user_id'      => $user->id,
            'email'        => $email,
            'ip_address'   => $request->ip(),
            'success'      => true,
            'attempted_at' => now(),
        ]);

        // Update last login timestamp
        $user->update(['last_login_at' => now()]);

        // Log the action
        AuditLog::record('login', 'users', $user->id);

        // Log the user in
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Redirect based on role
        return $this->redirectByRole($user);
    }

    // Handle logout
    public function logout(Request $request)
    {
        AuditLog::record('logout', 'users', Auth::id());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out successfully.');
    }

    // Redirect user to correct dashboard based on role
    private function redirectByRole(User $user): \Illuminate\Http\RedirectResponse
    {
        return match($user->role->name) {
            'admin'             => redirect()->route('admin.dashboard'),
            'researcher'        => redirect()->route('researcher.lobby'),
            'field_technician'  => redirect()->route('fieldtech.dashboard'),
            default             => redirect()->route('login'),
        };
    }
}
