<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Hapus kolom 'topic' yang lama (string)
            $table->dropColumn('topic');
            // Buat kolom 'topics' yang baru (json)
            $table->json('topics')->nullable()->after('course_code'); 
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('topics');
            $table->string('topic')->nullable()->after('course_code');
        });
    }
};