<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
            DB::statement('ALTER TABLE areas_of_interest ADD COLUMN boundary_polygon geometry(Polygon, 4326)');
            DB::statement('CREATE INDEX areas_of_interest_boundary_polygon_gist ON areas_of_interest USING GIST (boundary_polygon)');

            return;
        }

        Schema::table('areas_of_interest', function (Blueprint $table) {
            $table->text('boundary_polygon')->nullable()->after('boundary_coordinates');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS areas_of_interest_boundary_polygon_gist');
            DB::statement('ALTER TABLE areas_of_interest DROP COLUMN IF EXISTS boundary_polygon');

            return;
        }

        Schema::table('areas_of_interest', function (Blueprint $table) {
            $table->dropColumn('boundary_polygon');
        });
    }
};
