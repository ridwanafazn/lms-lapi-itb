<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel Users (Disesuaikan dengan HasUuids & Custom Fields Anda)
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary(); // PENTING: Pakai UUID, bukan ID biasa
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Custom fields dari Model Anda
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        // 2. Password Reset Tokens (Standar)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // 3. Sessions (Disesuaikan agar support UUID User)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            // PENTING: Ganti foreignId jadi foreignUuid agar cocok dengan tabel users
            $table->foreignUuid('user_id')->nullable()->index();

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
