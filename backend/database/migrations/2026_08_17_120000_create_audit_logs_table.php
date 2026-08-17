<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 slice 1: Postgres owns admin audit logs (store.json remains dual-write mirror).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->timestampTz('occurred_at')->index();
            $table->string('username', 64)->nullable()->index();
            $table->string('role', 32)->nullable();
            $table->string('role_label', 128)->nullable();
            $table->string('action', 64)->nullable()->index();
            $table->string('module', 128)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('device', 128)->nullable();
            $table->string('browser', 128)->nullable();
            $table->string('target_user', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
