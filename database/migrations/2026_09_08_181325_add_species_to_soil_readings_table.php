<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('soil_readings', function (Blueprint $table) {
            // Comes from the mobile app/probe submission itself, per
            // reading -- not a project-level setting. One of the four
            // species keys in App\Services\SpeciesReference::RANGES, or
            // null for older data / a submission that didn't include one.
            $table->string('species')->nullable()->after('point_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('soil_readings', function (Blueprint $table) {
            $table->dropColumn('species');
        });
    }
};
