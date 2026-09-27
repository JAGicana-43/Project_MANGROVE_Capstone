<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas_of_interest', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->string('name');
            $table->json('boundary_coordinates'); // array of [lat, lng] pairs forming the polygon
            $table->float('center_latitude')->nullable();
            $table->float('center_longitude')->nullable();
            $table->float('area_sqm')->nullable(); // computed area in square meters
            $table->string('status')->default('active'); // active, archived
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_of_interest');
    }
};
