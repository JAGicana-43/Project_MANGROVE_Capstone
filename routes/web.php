<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ResearcherUserController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\ForgotPasswordController;

/*
|--------------------------------------------------------------------------
| Public Routes (no login required)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('dashboard.landing');
})->name('landing');

Route::get('/login', [LoginController::class, 'showLogin'])->middleware('nocache')->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

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
    });

    // Researcher routes
    Route::middleware(['role:researcher'])->prefix('researcher')->name('researcher.')->group(function () {
        Route::get('/lobby', [DashboardController::class, 'lobby'])->name('lobby');
        Route::get('/dashboard', [DashboardController::class, 'lobby'])->name('dashboard');
        Route::post('/field-technicians', [ResearcherUserController::class, 'store'])->name('field-technicians.store');
    });

    Route::middleware(['role:researcher'])->prefix('projects')->name('projects.')->group(function () {
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/create/{section?}', [ProjectController::class, 'createSection'])->name('create-section');
        Route::post('/{project}/aoi/confirm', [ProjectController::class, 'confirmAoi'])->name('aoi.confirm');
        Route::post('/{project}/aoi/clear', [ProjectController::class, 'clearAoi'])->name('aoi.clear');
        Route::patch('/{project}/archive', [ProjectController::class, 'toggleArchive'])->name('archive');
        Route::post('/{project}/orthomosaic/upload', [ProjectController::class, 'uploadOrthomosaic'])->name('orthomosaic.upload');
        Route::get('/{project}/orthomosaic/{orthomosaic}/file', [ProjectController::class, 'downloadOrthomosaicFile'])->name('orthomosaic.file');
        Route::delete('/{project}/orthomosaic/{orthomosaic}', [ProjectController::class, 'removeOrthomosaic'])->name('orthomosaic.remove');
        Route::get('/{project}/{section}', [ProjectController::class, 'section'])->name('section');
    });

    // Field Technician routes
    Route::middleware(['role:field_technician'])->prefix('fieldtech')->name('fieldtech.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'fieldtech'])->name('dashboard');
    });

});
