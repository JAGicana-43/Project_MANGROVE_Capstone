<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Notification bell (partials/notification-bell.blade.php), shared
        // by both top-level layouts. A composer, not a per-controller
        // compact(), on purpose -- this codebase otherwise threads $user
        // through each controller explicitly, but doing that here would
        // mean touching every action across DashboardController,
        // ProjectController, SurveyComparisonController, ReportController,
        // AccountSettingsController, etc. that renders either layout, with
        // a real risk of missing one and crashing that page on an
        // undefined variable. This is the one place in the app that
        // genuinely needs to reach every page.
        View::composer(['layouts.lobby', 'layouts.workspace'], function ($view) {
            $user = Auth::user();

            $view->with([
                'unreadNotifications' => $user ? $user->unreadNotifications()->latest()->take(8)->get() : collect(),
                'unreadNotificationCount' => $user ? $user->unreadNotifications()->count() : 0,
            ]);
        });
    }
}
