<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suitability_surfaces', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('aoi_id')->constrained('areas_of_interest')->onDelete('cascade');
            $table->foreignUuid('run_id')->constrained('classification_runs')->onDelete('cascade');
            $table->string('interpolation_method')->default('idw'); // idw, kriging (future)
            $table->string('output_file_path');                     // path to generated raster/geojson
            $table->string('file_format')->default('geojson');      // geojson, tif
            $table->json('legend')->nullable();                      // color scale metadata
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suitability_surfaces');
    }
};
