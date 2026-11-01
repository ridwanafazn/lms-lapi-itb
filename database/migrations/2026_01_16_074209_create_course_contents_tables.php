<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->enum('type', ['modul_file', 'artikel_text', 'video']);
            $table->string('content_url')->nullable();
            $table->longText('body_text')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('deadline_days')->nullable();
            $table->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->integer('duration_minutes')->nullable();
            $table->float('passing_grade')->default(70);
            $table->enum('status', ['draft', 'active'])->default('draft');
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quiz_id')->constrained()->cascadeOnDelete();
            $table->longText('question_text');
            $table->enum('question_type', ['multiple_choice', 'essay'])->default('multiple_choice');
            $table->integer('score_weight')->default(1);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        // B. LEVEL ITEMS (WRAPPER / BRIDGE)
        Schema::create('level_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('level_id')->constrained('course_levels')->cascadeOnDelete();

            $table->integer('sort_order')->default(0);
            $table->enum('item_type', ['material', 'quiz', 'assignment']);

            $table->foreignUuid('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assignment_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('is_required')->default(true); // Wajib dikerjakan untuk lanjut?

            $table->timestamps();

            $table->index(['level_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_items');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('materials');
    }
};
