<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 - Menyimpan status timeout kuis.
 *
 * QuizPlayer sudah mengirim 'is_timeout' saat submit, tetapi kolomnya tidak
 * pernah ada sehingga nilainya dibuang diam-diam oleh mass assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->boolean('is_timeout')->default(false)->after('remaining_seconds');
            $table->timestamp('graded_at')->nullable()->after('is_timeout');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn(['is_timeout', 'graded_at']);
        });
    }
};
