<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\GisExportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SamplingPointAssignmentController;
use App\Http\Controllers\SurveyComparisonController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\ResearcherUserController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\NotificationController;
use App\Models\AreaOfInterest;
use App\Models\Project;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Public Routes (no login required)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    // Unlike /login (LoginController::showLogin), this route rendered the
    // public marketing page unconditionally -- so a signed-in user landing
    // here (new tab, bookmark, clicking the logo) saw the logged-out
    // marketing page and could easily read that as "got logged out,"
    // even though their session cookie was untouched the whole time.
    // Same role-based redirect LoginController already applies at /login.
    if (\Illuminate\Support\Facades\Auth::check()) {
        $role = \Illuminate\Support\Facades\Auth::user()->role->name;
        if ($role === 'admin') return redirect()->route('admin.dashboard');
        if ($role === 'researcher') return redirect()->route('researcher.lobby');
    }

    // Live counts from the database -- not hardcoded marketing numbers --
    // so the landing page never claims more than what's actually in the
    // system.
    $stats = [
        'researchers' => User::whereHas('role', fn ($q) => $q->where('name', 'researcher'))
            ->where('is_active', true)
            ->count(),
        'projects' => Project::count(),
        'hectares' => round(AreaOfInterest::where('status', 'active')->sum('area_sqm') / 10000, 1),
    ];

    return view('dashboard.landing', ['stats' => $stats]);
})->name('landing');

Route::get('/login', [LoginController::class, 'showLogin'])->middleware('nocache')->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Field technicians don't get a web session at all -- their accounts are
// mobile-app-only, so this is where LoginController sends them instead of
// authenticating them into a dashboard that shouldn't exist for their role.
Route::get('/mobile-app-required', [LoginController::class, 'mobileRequired'])
    ->middleware('nocache')
    ->name('mobile.required');

Route::middleware('nocache')->name('password.')->group(function () {
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showRequestForm'])->name('request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('update');
});

/*
|--------------------------------------------------------------------------
| Protected Routes (login required)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'nocache'])->group(function () {

    // Account settings — reachable by any authenticated role (no role:
    // middleware), since changing your own password isn't role-specific.
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/account', [AccountSettingsController::class, 'edit'])->name('account');
        Route::put('/account/password', [AccountSettingsController::class, 'updatePassword'])->name('account.password');
    });

    // Notification bell (layouts/lobby.blade.php + layouts/workspace.blade.php)
    // -- same "any authenticated role" reasoning as settings above, though
    // in practice only researchers get any (field technicians don't get a
    // web session at all).
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Admin routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
        Route::post('/users/{user}/reject', [AdminUserController::class, 'reject'])->name('users.reject');
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
        Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/bulk-approve', [AdminUserController::class, 'bulkApprove'])->name('users.bulk-approve');
        Route::post('/users/bulk-reject', [AdminUserController::class, 'bulkReject'])->name('users.bulk-reject');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    });

    // Researcher routes
    Route::middleware(['role:researcher'])->prefix('researcher')->name('researcher.')->group(function () {
        Route::get('/lobby', [DashboardController::class, 'lobby'])->name('lobby');
        Route::get('/dashboard', [DashboardController::class, 'lobby'])->name('dashboard');
        Route::post('/field-technicians', [ResearcherUserController::class, 'store'])->name('field-technicians.store');
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    });

    Route::middleware(['role:researcher'])->prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/create/{section?}', [ProjectController::class, 'createSection'])->name('create-section');
        Route::post('/{project}/aoi/confirm', [ProjectController::class, 'confirmAoi'])->name('aoi.confirm');
        Route::post('/{project}/aoi/clear', [ProjectController::class, 'clearAoi'])->name('aoi.clear');
        Route::patch('/{project}/archive', [ProjectController::class, 'toggleArchive'])->name('archive');
        Route::patch('/{project}/rename', [ProjectController::class, 'rename'])->name('rename');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/{project}/orthomosaic/upload', [ProjectController::class, 'uploadOrthomosaic'])->name('orthomosaic.upload');
        Route::get('/{project}/orthomosaic/{orthomosaic}/file', [ProjectController::class, 'downloadOrthomosaicFile'])->name('orthomosaic.file');
        Route::get('/{project}/orthomosaic/{orthomosaic}/preview', [ProjectController::class, 'orthomosaicPreview'])->name('orthomosaic.preview');
        Route::delete('/{project}/orthomosaic/{orthomosaic}', [ProjectController::class, 'removeOrthomosaic'])->name('orthomosaic.remove');
        Route::get('/field-technicians', [SamplingPointAssignmentController::class, 'technicians'])->name('field-technicians.index');
        Route::post('/{project}/sampling-points/{point}/assign', [SamplingPointAssignmentController::class, 'assign'])->name('sampling-points.assign');
        Route::post('/{project}/sampling-points/assign-all', [SamplingPointAssignmentController::class, 'assignAll'])->name('sampling-points.assign-all');
        Route::post('/{project}/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
        Route::get('/{project}/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
        Route::get('/{project}/gis-export', [GisExportController::class, 'export'])->name('gis-export');
        Route::get('/{project}/comparisons', [SurveyComparisonController::class, 'index'])->name('comparisons.index');
        Route::post('/{project}/comparisons', [SurveyComparisonController::class, 'store'])->name('comparisons.store');
        Route::get('/{project}/comparisons/{aoi}/timeline', [SurveyComparisonController::class, 'timeline'])->name('comparisons.timeline');
        Route::get('/{project}/comparisons/{comparison}', [SurveyComparisonController::class, 'show'])->name('comparisons.show');
        Route::get('/{project}/{section}', [ProjectController::class, 'section'])->name('section');
    });

});
