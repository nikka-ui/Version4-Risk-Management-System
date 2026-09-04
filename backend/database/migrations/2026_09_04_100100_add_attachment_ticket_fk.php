<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft integrity: orphan attachment rows for missing tickets are removed,
 * then an FK from risk_attachments.ticket_ref → risk_tickets.reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('risk_attachments') || ! Schema::hasTable('risk_tickets')) {
            return;
        }

        // Driver-safe orphan cleanup (SQLite rejects `DELETE FROM t alias` syntax).
        DB::table('risk_attachments')
            ->whereNotIn('ticket_ref', DB::table('risk_tickets')->select('reference'))
            ->delete();

        try {
            Schema::table('risk_attachments', function (Blueprint $table) {
                $table->foreign('ticket_ref')
                    ->references('reference')
                    ->on('risk_tickets')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        } catch (\Throwable) {
            // Already exists or driver limitation.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('risk_attachments', function (Blueprint $table) {
                $table->dropForeign(['ticket_ref']);
            });
        } catch (\Throwable) {
            // ignore
        }
    }
};
