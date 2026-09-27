<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orthomosaics', function (Blueprint $table) {
            // The model_id (from summary.json) the labels in
            // last_cluster_labels were actually submitted against. Without
            // this, the labeling pre-fill matched purely by cluster_id
            // index, with no way to tell whether a later run's clusters
            // mean the same thing -- verified this actually broke real
            // labels: swapping the feature extractor changed what every
            // cluster_id represents, but the pre-fill blindly carried the
            // old index-to-label mapping forward anyway, producing
            // confidently-wrong defaults that got submitted as-is.
            $table->string('last_cluster_labels_model_id')->nullable()->after('last_cluster_labels');
        });
    }

    public function down(): void
    {
        Schema::table('orthomosaics', function (Blueprint $table) {
            $table->dropColumn('last_cluster_labels_model_id');
        });
    }
};
