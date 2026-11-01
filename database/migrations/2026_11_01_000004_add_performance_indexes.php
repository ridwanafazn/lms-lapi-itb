<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.7 - Index untuk query panas.
 *
 * Catatan: constraint quiz_answers memakai tiga kolom (bukan dua) karena satu
 * soal multi-jawaban memang menghasilkan beberapa baris per question_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->unique(
                ['quiz_attempt_id', 'question_id', 'question_option_id'],
                'quiz_answers_attempt_question_option_unique'
            );
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->index(['quiz_id', 'user_id', 'score'], 'quiz_attempts_quiz_user_score_index');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->unique(
                ['assignment_id', 'user_id', 'batch_id'],
                'submissions_assignment_user_batch_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->dropUnique('quiz_answers_attempt_question_option_unique');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex('quiz_attempts_quiz_user_score_index');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropUnique('submissions_assignment_user_batch_unique');
        });
    }
};
