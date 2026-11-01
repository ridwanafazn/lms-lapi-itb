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
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('quiz_attempt_id')
                  ->constrained('quiz_attempts')
                  ->cascadeOnDelete();

            $table->foreignUuid('question_id')
                  ->constrained('questions')
                  ->cascadeOnDelete();

            $table->foreignUuid('question_option_id')
                  ->nullable()
                  ->constrained('question_options')
                  ->nullOnDelete();

            $table->longText('essay_answer')->nullable();

            $table->decimal('score', 8, 2)->default(0);

            $table->text('instructor_feedback')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
