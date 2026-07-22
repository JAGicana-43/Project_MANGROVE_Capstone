<?php

use App\Http\Controllers\Api\MissionGenerationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/missions/generate', [MissionGenerationController::class, 'generate'])
        ->name('missions.generate');

    Route::get('/missions/{missionRequest}/download', [MissionGenerationController::class, 'download'])
        ->name('missions.download');
});
