<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mission_requests', function (Blueprint $table) {
            // Previously hardcoded to 'goHome'/'goHome'/the drone's own
            // config default -- now request-adjustable (gated behind the
            // admin's "advanced mission settings" toggle), so they need to
            // be persisted the same way altitude/overlap/gimbal already are
            // for the Mission Parameters form to restore correctly on reload.
            $table->string('finish_action')->nullable()->after('waypoint_speed_mps');
            $table->string('execute_rc_lost_action')->nullable()->after('finish_action');
            $table->float('transitional_speed_mps')->nullable()->after('execute_rc_lost_action');
        });
    }

    public function down(): void
    {
        Schema::table('mission_requests', function (Blueprint $table) {
            $table->dropColumn(['finish_action', 'execute_rc_lost_action', 'transitional_speed_mps']);
        });
    }
};
