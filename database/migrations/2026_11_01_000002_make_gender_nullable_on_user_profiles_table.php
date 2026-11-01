<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.6 - `gender` semula ENUM('L','P').
 * Menyimpan string kosong memicu "Data truncated for column 'gender'",
 * sehingga menyimpan profil tanpa memilih gender gagal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->enum('gender', ['L', 'P'])->nullable()->change();
        });
    }
};
