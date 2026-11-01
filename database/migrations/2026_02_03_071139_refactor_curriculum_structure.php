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
        // 1. Rename Tables (Core Structure)
        // Sebelum rename course_levels, kita harus drop foreign key di level_items yang mengarah ke sana
        if (Schema::hasTable('level_items')) {
            Schema::table('level_items', function (Blueprint $table) {
                // Drop FK lama: level_items_level_id_foreign
                $table->dropForeign(['level_id']);
            });
        }

        // Rename: course_levels -> sections
        if (Schema::hasTable('course_levels')) {
            Schema::rename('course_levels', 'sections');
        }

        // Rename: level_items -> lessons
        if (Schema::hasTable('level_items')) {
            Schema::rename('level_items', 'lessons');
        }

        // 2. Fix Relationships in 'lessons' table (Formerly level_items)
        Schema::table('lessons', function (Blueprint $table) {
            // Rename column level_id -> section_id
            $table->renameColumn('level_id', 'section_id');
        });

        // Add FK baru mengarah ke sections
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreign('section_id')
                  ->references('id')->on('sections')
                  ->cascadeOnDelete();
        });

        // Fix column in sections table (Formerly course_levels)
        Schema::table('sections', function (Blueprint $table) {
             // Pastikan course_id constraint tetap aman (biasanya aman saat rename table)
             // Tidak perlu aksi khusus jika DB engine support rename (MySQL InnoDB support)
        });

        // 3. Move Ownership (Instructor -> Course)
        $contentTables = ['materials', 'quizzes', 'assignments'];

        foreach ($contentTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                // Hapus FK ke User (Instructor)
                // Kita gunakan array syntax agar Laravel mencari nama constraint otomatis
                // [tableName]_instructor_id_foreign
                if (Schema::hasColumn($table->getTable(), 'instructor_id')) {
                     $table->dropForeign(['instructor_id']);
                     $table->dropColumn('instructor_id');
                }

                // Tambah FK ke Courses
                if (!Schema::hasColumn($table->getTable(), 'course_id')) {
                    $table->foreignUuid('course_id')
                          ->nullable() // Boleh null dulu untuk migrasi data lama
                          ->after('id')
                          ->constrained('courses')
                          ->cascadeOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Revert Ownership (Course -> Instructor)
        $contentTables = ['materials', 'quizzes', 'assignments'];

        foreach ($contentTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['course_id']);
                $table->dropColumn('course_id');

                $table->uuid('instructor_id')->nullable()->after('id');
                $table->foreign('instructor_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 2. Revert Table Renaming
        // Drop FK di lessons dulu sebelum rename balik parentnya
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->renameColumn('section_id', 'level_id');
        });

        Schema::rename('lessons', 'level_items');
        Schema::rename('sections', 'course_levels');

        // Restore FK lama
        Schema::table('level_items', function (Blueprint $table) {
             $table->foreign('level_id')
                  ->references('id')->on('course_levels')
                  ->cascadeOnDelete();
        });
    }
};
