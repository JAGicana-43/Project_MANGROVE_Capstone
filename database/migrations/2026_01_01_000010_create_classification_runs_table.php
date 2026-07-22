<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classification_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('orthomosaic_id')->constrained('orthomosaics')->onDelete('cascade');
            $table->string('model_version')->default('resnet18'); // track which AI model version was used
            $table->float('confidence_threshold')->default(0.7);
            $table->integer('total_tiles')->default(0);
            $table->integer('processed_tiles')->default(0);
            $table->string('status')->default('queued'); // queued, running, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classification_runs');
    }
};
