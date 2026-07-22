<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soil_readings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('point_id')->constrained('sampling_points')->onDelete('cascade');
            $table->foreignUuid('collected_by')->constrained('users')->onDelete('restrict');

            // averaged sensor values from ESP32 (20 readings per point)
            $table->float('ph_avg')->nullable();
            $table->float('salinity_avg')->nullable();        // in mS/cm
            $table->float('moisture_avg')->nullable();        // in percentage
            $table->float('nitrogen_avg')->nullable();        // in ppm
            $table->float('phosphorus_avg')->nullable();      // in ppm
            $table->float('potassium_avg')->nullable();       // in ppm
            $table->integer('reading_count')->default(20);    // number of readings averaged

            // suitability assessment
            $table->string('suitability_rating')->nullable(); // suitable, marginal, unsuitable
            $table->text('remarks')->nullable();

            // sync status with mobile
            $table->boolean('is_synced')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soil_readings');
    }
};
