<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PROGRAMS
        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. COURSES
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->enum('delivery_mode', ['self_paced', 'cohort_based'])->default('cohort_based');
            $table->string('thumbnail_url')->nullable();

            // Creator/Owner Course (Untuk referensi awal, instruktur pengajar ada di tabel 'instructors')
            $table->foreignUuid('instructor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // 3. PIVOT: Program - Course
        Schema::create('program_courses', function (Blueprint $table) {
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained()->cascadeOnDelete();
            $table->integer('order_index')->default(0);
            $table->primary(['program_id', 'course_id']);
        });

        // 4. COURSE LEVELS
        Schema::create('course_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_levels');
        Schema::dropIfExists('program_courses');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('programs');
    }
};
