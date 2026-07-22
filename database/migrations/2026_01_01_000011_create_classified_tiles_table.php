<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_tiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('run_id')->constrained('classification_runs')->onDelete('cascade');
            $table->integer('tile_x');                        // tile column index in the grid
            $table->integer('tile_y');                        // tile row index in the grid
            $table->string('land_cover_type');                // e.g. mangrove, water, bare_soil, built_up
            $table->float('confidence');                      // model confidence score 0.0 - 1.0
            $table->float('center_latitude')->nullable();
            $table->float('center_longitude')->nullable();
            $table->json('tile_bounds')->nullable();          // [min_lat, min_lng, max_lat, max_lng]
            $table->timestamp('created_at')->useCurrent();

            $table->index(['run_id', 'land_cover_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_tiles');
    }
};
