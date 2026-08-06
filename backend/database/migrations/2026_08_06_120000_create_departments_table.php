<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64)->unique();
            $table->string('name', 128);
            $table->string('code', 32);
            $table->text('description')->nullable();
            $table->string('head', 128)->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('active')->default(true);
            $table->boolean('auto_approve_low_moderate')->default(false);
            $table->timestamps();

            $table->index(['active', 'name']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
