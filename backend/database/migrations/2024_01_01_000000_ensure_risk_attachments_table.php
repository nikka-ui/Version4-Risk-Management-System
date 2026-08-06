<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compatible with docker/postgres/init/01_risk_attachments.sql and Express web.
 * Creates the table only when missing; never drops it (shared with docker/web).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('risk_attachments')) {
            return;
        }

        Schema::create('risk_attachments', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('ticket_ref', 32);
            $table->string('original_name', 255);
            $table->string('mime_type', 128)->default('application/octet-stream');
            $table->bigInteger('size_bytes')->default(0);
            $table->string('storage_key', 512);
            $table->string('uploaded_by', 64)->nullable();
            $table->boolean('legacy')->default(false);
            $table->timestampTz('uploaded_at')->useCurrent();

            $table->index('ticket_ref', 'idx_risk_attachments_ticket_ref');
        });
    }

    public function down(): void
    {
        // Intentionally empty: risk_attachments is shared with Express and must not be dropped.
    }
};
