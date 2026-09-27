<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres does not auto-index foreign key columns (unlike MySQL/InnoDB) --
 * confirmed directly via pg_indexes that several foreign keys queried on
 * every Orthomosaic/AOI workspace page load had no index at all beyond the
 * primary key, meaning each lookup was a full table scan. sampling_grids
 * and classified_tiles already had the right indexes when their tables
 * were created; these four were missed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classification_runs', function (Blueprint $table) {
            // $activeOrthomosaic->classificationRuns()->latest()->first()
            $table->index('orthomosaic_id');
        });

        Schema::table('suitability_surfaces', function (Blueprint $table) {
            // $activeClassificationRun->suitabilitySurfaces()->latest()->first()
            $table->index('run_id');
            $table->index('aoi_id');
        });

        Schema::table('orthomosaics', function (Blueprint $table) {
            // $activeMission->orthomosaics()->latest()->first()
            $table->index('mission_id');
            $table->index('aoi_id');
        });

        Schema::table('sampling_points', function (Blueprint $table) {
            // SamplingPoint::whereIn('grid_id', ...) in workspace.blade.php
            $table->index('grid_id');
        });
    }

    public function down(): void
    {
        Schema::table('classification_runs', function (Blueprint $table) {
            $table->dropIndex(['orthomosaic_id']);
        });

        Schema::table('suitability_surfaces', function (Blueprint $table) {
            $table->dropIndex(['run_id']);
            $table->dropIndex(['aoi_id']);
        });

        Schema::table('orthomosaics', function (Blueprint $table) {
            $table->dropIndex(['mission_id']);
            $table->dropIndex(['aoi_id']);
        });

        Schema::table('sampling_points', function (Blueprint $table) {
            $table->dropIndex(['grid_id']);
        });
    }
};
