<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mission_requests', function (Blueprint $table) {
            // Raw waypoint list (lat/lng/heading/headingEnable per point) —
            // previously only ever existed in the one-time JSON response and
            // inside the KMZ binary, so the flight path/summary disappeared
            // from the workspace on refresh with nothing to reload from.
            $table->json('waypoints')->nullable()->after('gimbal_angle');
            // Was used to build the KMZ and compute the endurance check but
            // never persisted, so a reloaded mission couldn't accurately
            // recompute its own estimated flight time.
            $table->float('waypoint_speed_mps')->nullable()->after('gimbal_angle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mission_requests', function (Blueprint $table) {
            $table->dropColumn(['waypoints', 'waypoint_speed_mps']);
        });
    }
};
