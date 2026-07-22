<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignUuid('generated_by')->constrained('users')->onDelete('restrict');
            $table->string('title');
            $table->string('report_type'); // classification, suitability, comparison, soil, full_survey
            $table->string('file_path');
            $table->string('file_format')->default('pdf'); // pdf, csv, xlsx
            $table->integer('download_count')->default(0);
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
