<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drone_missions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aoi_id')->constrained('areas_of_interest')->onDelete('cascade');
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->float('altitude_m')->nullable();       // flight altitude in meters
            $table->float('overlap_pct')->nullable();      // image overlap percentage
            $table->float('speed_mps')->nullable();        // drone speed in m/s
            $table->string('kmz_file_path')->nullable();   // generated KMZ mission file path
            $table->string('status')->default('planned');  // planned, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drone_missions');
    }
};
