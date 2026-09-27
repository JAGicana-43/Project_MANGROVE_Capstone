<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function complete(Request $request)
    {
        $user = Auth::user();

        if (is_null($user->onboarding_completed_at)) {
            $user->update(['onboarding_completed_at' => now()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Onboarding marked complete.']);
        }

        return back();
    }
}
