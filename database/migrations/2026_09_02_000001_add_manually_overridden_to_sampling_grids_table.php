<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sampling_grids', function (Blueprint $table) {
            // Tracks whether a researcher directly overrode this cell's
            // label (vs. it being whatever the cluster-vote/water-detection
            // pipeline produced) -- lets the UI show which cells were
            // human-verified, and is honest provenance if this ever needs
            // defending ("was this AI or a person's judgment call?").
            $table->boolean('manually_overridden')->default(false)->after('suitability_score');
        });
    }

    public function down(): void
    {
        Schema::table('sampling_grids', function (Blueprint $table) {
            $table->dropColumn('manually_overridden');
        });
    }
};
