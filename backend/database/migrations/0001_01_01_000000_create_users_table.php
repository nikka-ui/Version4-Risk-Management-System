<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users table aligned with Express store.json identity fields.
     * Passwords are bcrypt-hashed in Laravel; Express store.json remains
     * plaintext until a later cutover. Browser login stays on Express.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 64)->default('employee');
            $table->string('role_label', 128)->nullable();
            $table->string('employee_id', 64)->nullable()->index();
            $table->string('department', 128)->nullable();
            $table->string('position', 128)->nullable();
            $table->boolean('can_manage_users')->default(false);
            $table->boolean('built_in')->default(false);
            $table->boolean('active')->default(true);
            $table->string('status', 32)->default('active');
            $table->boolean('deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
