<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Materials
        if (Schema::hasTable('materials')) {
            Schema::table('materials', function (Blueprint $table) {
                if (!Schema::hasColumn('materials', 'instructor_id')) {
                    // Gunakan uuid() bukan foreignId() agar cocok dengan table users
                    $table->uuid('instructor_id')->nullable()->after('id');
                    $table->foreign('instructor_id')->references('id')->on('users')->onDelete('cascade');
                }
            });
        }

        // Tabel Quizzes
        if (Schema::hasTable('quizzes')) {
            Schema::table('quizzes', function (Blueprint $table) {
                if (!Schema::hasColumn('quizzes', 'instructor_id')) {
                    $table->uuid('instructor_id')->nullable()->after('id');
                    $table->foreign('instructor_id')->references('id')->on('users')->onDelete('cascade');
                }
            });
        }

        // Tabel Assignments
        if (Schema::hasTable('assignments')) {
            Schema::table('assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('assignments', 'instructor_id')) {
                    $table->uuid('instructor_id')->nullable()->after('id');
                    $table->foreign('instructor_id')->references('id')->on('users')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->dropColumn('instructor_id');
        });
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->dropColumn('instructor_id');
        });
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->dropColumn('instructor_id');
        });
    }
};
