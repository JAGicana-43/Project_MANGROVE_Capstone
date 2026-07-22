<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->json('aoi_polygon');
            $table->string('drone_key');
            $table->float('altitude');
            $table->float('overlap_percent');
            $table->float('gimbal_angle');
            $table->unsignedInteger('waypoint_count')->nullable();
            $table->float('line_spacing_meters')->nullable();
            $table->float('total_distance_meters')->nullable();
            $table->string('status')->default('pending');
            $table->string('kmz_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_requests');
    }
};
