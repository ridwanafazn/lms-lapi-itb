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
        Schema::table('quiz_attempts', function (Blueprint $table) {
            // Ubah kolom score agar boleh kosong (NULL)
            // Default 0 atau NULL sama-sama bisa, tapi NULL lebih tepat untuk "belum dinilai"
            $table->decimal('score', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            // Kembalikan ke tidak boleh null
            $table->decimal('score', 5, 2)->nullable(false)->default(0)->change();
        });
    }
};
