<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 slice 9: mirror Express store.notifications.
 * Laravel-owned copy; Express store.json remains the live SoT while USE_LARAVEL_API is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('recipient_username', 64)->nullable()->index();
            $table->string('recipient_role', 32)->nullable()->index();
            $table->string('type', 64);
            $table->string('title', 512);
            $table->text('message')->nullable();
            $table->string('ticket_ref', 32)->nullable()->index();
            $table->string('href', 512)->nullable();
            $table->string('from_username', 64)->nullable();
            $table->string('from_name', 128)->nullable();
            $table->string('from_role', 32)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
