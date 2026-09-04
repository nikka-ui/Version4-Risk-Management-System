<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('risk_tickets', 'response_due_at')) {
                $table->timestampTz('response_due_at')->nullable()->after('routed_at');
                $table->index('response_due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('risk_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('risk_tickets', 'response_due_at')) {
                $table->dropIndex(['response_due_at']);
                $table->dropColumn('response_due_at');
            }
        });
    }
};
