<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 slice 2: persist each AI classify run (ticket.ai remains live display SoT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_reference', 32)->nullable()->index();
            $table->string('source', 32)->index();
            $table->string('risk_category', 64)->nullable()->index();
            $table->unsignedTinyInteger('likelihood')->nullable();
            $table->unsignedTinyInteger('impact')->nullable();
            $table->unsignedTinyInteger('severity')->nullable();
            $table->decimal('confidence', 4, 2)->nullable();
            $table->string('responsible_department', 128)->nullable();
            $table->string('priority', 32)->nullable();
            $table->json('input')->nullable();
            $table->json('result');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_results');
    }
};
