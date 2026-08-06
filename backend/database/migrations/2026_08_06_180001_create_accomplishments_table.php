<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 slice 1: mirror Express store.accomplishments.
 * Linked by ticket_ref (RISK-…) — Express remains live SoT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accomplishments', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64)->unique();
            $table->string('ticket_ref', 32)->index();
            $table->string('ticket_title', 512)->nullable();
            $table->text('summary')->nullable();
            $table->text('outcomes')->nullable();
            $table->string('submitted_by', 64)->nullable();
            $table->string('submitted_by_name', 128)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->json('evidence')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accomplishments');
    }
};
