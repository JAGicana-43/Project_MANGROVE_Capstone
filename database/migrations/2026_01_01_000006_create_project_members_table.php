<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role')->default('member'); // lead, member, observer
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['project_id', 'user_id']); // no duplicate members
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};
