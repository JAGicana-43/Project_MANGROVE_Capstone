<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action');          // created, updated, deleted, login, logout, failed_login, exported
            $table->string('table_name')->nullable();  // which table was affected
            $table->uuid('record_id')->nullable();     // which record was affected
            $table->json('payload')->nullable();       // before/after values or relevant context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('logged_at')->useCurrent();

            $table->index(['user_id', 'logged_at']);
            $table->index(['table_name', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
