<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 slice 9: mirror Express store.reportLogs (append-only audit stream).
 * Laravel-owned copy; Express store.json remains the live SoT while USE_LARAVEL_API is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_logs', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('ticket_ref', 32)->nullable()->index();
            $table->string('title', 512)->nullable();
            $table->string('submitted_by', 64)->nullable()->index();
            $table->string('submitter_role', 32)->nullable();
            $table->string('status', 64)->nullable();
            $table->string('action', 64)->nullable();
            $table->text('detail')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_logs');
    }
};
