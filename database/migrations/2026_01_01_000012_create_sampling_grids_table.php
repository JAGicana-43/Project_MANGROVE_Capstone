<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sampling_grids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('classification_runs')->onDelete('cascade');
            $table->integer('grid_x');                        // grid column index
            $table->integer('grid_y');                        // grid row index
            $table->string('dominant_cover')->nullable();     // most common land cover in this cell
            $table->float('suitability_score')->nullable();   // 0.0 - 1.0 computed suitability
            $table->json('cell_bounds')->nullable();          // [min_lat, min_lng, max_lat, max_lng]
            $table->float('center_latitude')->nullable();
            $table->float('center_longitude')->nullable();
            $table->timestamp('computed_at')->useCurrent();

            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sampling_grids');
    }
};
