<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sampling_points', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('grid_id')->constrained('sampling_grids')->onDelete('cascade');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->float('latitude');
            $table->float('longitude');
            $table->integer('priority_rank')->nullable();     // 1 = highest priority
            $table->string('status')->default('pending');     // pending, transmitted, collected, skipped
            $table->timestamp('transmitted_at')->nullable();  // when sent to mobile app
            $table->timestamp('collected_at')->nullable();    // when field data was collected
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sampling_points');
    }
};
