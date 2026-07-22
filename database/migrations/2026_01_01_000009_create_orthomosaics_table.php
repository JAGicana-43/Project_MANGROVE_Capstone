<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orthomosaics', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('aoi_id')->constrained('areas_of_interest')->onDelete('cascade');
            $table->foreignUuid('mission_id')->nullable()->constrained('drone_missions')->onDelete('set null');
            $table->foreignUuid('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->string('file_path');                   // path to the orthomosaic image file
            $table->string('file_format')->default('tif'); // tif, png, jpg
            $table->float('resolution_cm')->nullable();    // ground resolution in cm/pixel
            $table->json('bounds_coordinates')->nullable(); // bounding box [min_lat, min_lng, max_lat, max_lng]
            $table->string('coordinate_system')->default('WGS84');
            $table->string('status')->default('pending'); // pending, processing, ready, failed
            $table->timestamp('captured_at')->nullable();  // date/time of drone capture
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orthomosaics');
    }
};
