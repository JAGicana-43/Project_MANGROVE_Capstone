<?php

use App\Http\Controllers\Api\ClassificationController;
use App\Http\Controllers\Api\MissionGenerationController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileSyncController;
use Illuminate\Support\Facades\Route;

// Field technician mobile app -- token auth (Sanctum), not the web session
// used by everything else in this file. See MobileAuthController's docblock
// for why these accounts can't use the 'web'-guard routes below.
Route::prefix('mobile')->name('mobile.')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [MobileAuthController::class, 'logout'])->name('logout');
        Route::get('/assigned-points', [MobileSyncController::class, 'assignedPoints'])->name('assigned-points');
        Route::post('/points/{point}/soil-reading', [MobileSyncController::class, 'submitReading'])->name('soil-reading');
    });
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/missions/generate', [MissionGenerationController::class, 'generate'])
        ->name('missions.generate');

    Route::get('/missions/{missionRequest}/download', [MissionGenerationController::class, 'download'])
        ->name('missions.download');

    Route::post('/orthomosaics/{orthomosaic}/classify', [ClassificationController::class, 'runClassification'])
        ->name('classification.run');

    Route::get('/classification-runs/{classificationRun}/status', [ClassificationController::class, 'status'])
        ->name('classification.status');

    Route::post('/classification-runs/{classificationRun}/cancel', [ClassificationController::class, 'cancel'])
        ->name('classification.cancel');

    Route::get('/classification-runs/{classificationRun}/clusters', [ClassificationController::class, 'getClusters'])
        ->name('classification.clusters');

    Route::post('/classification-runs/{classificationRun}/labels', [ClassificationController::class, 'submitLabels'])
        ->name('classification.labels');

    Route::post('/classification-runs/{classificationRun}/sampling-points', [ClassificationController::class, 'generateSamplingPoints'])
        ->name('classification.sampling-points');

    Route::get('/classification-runs/{classificationRun}/overlay', [ClassificationController::class, 'serveOverlay'])
        ->name('classification.overlay');

    Route::get('/classification-runs/{classificationRun}/grid-cells', [ClassificationController::class, 'getGridCells'])
        ->name('classification.grid-cells');

    Route::patch('/classification-runs/{classificationRun}/grid-cells/{gridCell}', [ClassificationController::class, 'updateGridCell'])
        ->name('classification.grid-cells.update');

    Route::get('/classification-runs/{classificationRun}/sample-tiles/{filename}', [ClassificationController::class, 'serveSampleTile'])
        ->name('classification.sample-tile');
});
