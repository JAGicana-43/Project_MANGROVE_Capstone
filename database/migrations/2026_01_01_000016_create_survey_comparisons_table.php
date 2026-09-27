<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_comparisons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aoi_id')->constrained('areas_of_interest')->onDelete('cascade');
            $table->foreignUuid('orthomosaic_a_id')->constrained('orthomosaics')->onDelete('restrict'); // earlier survey
            $table->foreignUuid('orthomosaic_b_id')->constrained('orthomosaics')->onDelete('restrict'); // later survey
            $table->foreignUuid('generated_by')->constrained('users')->onDelete('restrict');
            $table->json('change_summary')->nullable();    // breakdown of land cover change per class
            $table->string('report_file_path')->nullable(); // path to comparison report/map
            $table->text('notes')->nullable();
            $table->timestamp('compared_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_comparisons');
    }
};
