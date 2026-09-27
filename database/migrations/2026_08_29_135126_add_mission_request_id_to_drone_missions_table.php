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
        Schema::table('drone_missions', function (Blueprint $table) {
            // Links back to the mission_requests audit row that actually
            // generated this mission's KMZ — that's where the waypoint list,
            // drone_key, and gimbal_angle live (drone_missions itself never
            // stored those), so the workspace can reload/re-render a
            // previously generated flight path on page load.
            $table->foreignId('mission_request_id')->nullable()->after('aoi_id')
                ->constrained('mission_requests')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drone_missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mission_request_id');
        });
    }
};
