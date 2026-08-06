<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 slice 1: mirror Express store.riskTickets for import + read APIs.
 * Express/store.json remains source of truth for the live workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64)->unique();
            $table->string('reference', 32)->unique();
            $table->string('title', 512)->nullable();
            $table->text('description')->nullable();
            $table->string('location', 512)->nullable();
            $table->string('status', 64)->default('draft')->index();
            $table->string('category', 64)->nullable()->index();
            $table->string('priority', 32)->nullable()->index();
            $table->string('department', 128)->nullable()->index();
            $table->string('reporter_department', 128)->nullable();
            $table->unsignedTinyInteger('likelihood')->nullable();
            $table->unsignedTinyInteger('impact')->nullable();
            $table->unsignedSmallInteger('risk_score')->nullable();
            $table->string('submitted_by', 64)->nullable()->index();
            $table->string('submitted_by_name', 128)->nullable();
            $table->text('mitigation_approach')->nullable();
            $table->unsignedInteger('evidence_count')->default(0);
            $table->string('accomplishment_external_id', 64)->nullable();
            $table->timestampTz('source_created_at')->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('routed_at')->nullable();
            $table->timestampTz('mitigation_due_at')->nullable();
            $table->boolean('deleted')->default(false)->index();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('deleted_by', 64)->nullable();
            $table->string('deleted_by_name', 128)->nullable();
            $table->text('deletion_reason')->nullable();
            // json works on Postgres + SQLite tests (Postgres still stores efficiently)
            $table->json('five_w1h')->nullable();
            $table->json('ai')->nullable();
            $table->json('ownership')->nullable();
            $table->json('action_plan')->nullable();
            $table->json('personnel')->nullable();
            $table->json('progress_updates')->nullable();
            $table->json('reassignments')->nullable();
            $table->json('audit_trail')->nullable();
            $table->json('thread_comments')->nullable();
            $table->json('private_comments')->nullable();
            $table->json('executive_comments')->nullable();
            $table->json('mitigation_plan_history')->nullable();
            $table->json('reopen_history')->nullable();
            $table->json('president_plan_decision')->nullable();
            $table->json('president_final_decision')->nullable();
            $table->json('president_decision')->nullable();
            $table->json('closure')->nullable();
            $table->json('final_resolution')->nullable();
            $table->json('rmu_recommendations')->nullable();
            $table->json('escalations')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_tickets');
    }
};
