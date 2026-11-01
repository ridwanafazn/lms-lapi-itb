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
        Schema::table('courses', function (Blueprint $table) {
            $table->string('course_code')->unique()->nullable()->after('title');
            $table->string('topic')->nullable()->after('course_code'); // Penting untuk filter Topic di UI
            $table->json('tools_used')->nullable()->after('description');
            $table->text('target_audience')->nullable()->after('tools_used');
            $table->text('prerequisites')->nullable()->after('target_audience');
            $table->json('learning_outcomes')->nullable()->after('prerequisites');
            $table->string('language')->default('Indonesia')->after('level');
            $table->text('curriculum_summary')->nullable()->after('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'course_code',
                'topic',
                'tools_used',
                'target_audience',
                'prerequisites',
                'learning_outcomes',
                'language',
                'curriculum_summary'
            ]);
        });
    }
};