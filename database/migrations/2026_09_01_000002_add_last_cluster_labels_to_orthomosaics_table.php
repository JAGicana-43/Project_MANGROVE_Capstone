<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orthomosaics', function (Blueprint $table) {
            // {cluster_id: label} from the most recently submitted labeling
            // pass on this orthomosaic -- pre-fills the labeling form on a
            // later re-run of the same file instead of starting blank every
            // time. Keyed by cluster_id, not label, since that's what a new
            // run's clusters need to look up against; safe to reuse across
            // runs because the same file + same pipeline config deterministically
            // reproduces the same k-sweep result and cluster assignment.
            $table->json('last_cluster_labels')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orthomosaics', function (Blueprint $table) {
            $table->dropColumn('last_cluster_labels');
        });
    }
};
