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
        // 1. Tabel Topik Diskusi (Threads)
        Schema::create('discussions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade'); // Penanya

            $table->string('title');
            $table->text('body'); // Isi pertanyaan (bisa HTML/Markdown)

            $table->boolean('is_solved')->default(false); // Status Solved
            $table->boolean('is_locked')->default(false); // Admin/Instructor bisa lock

            $table->timestamps();

            // Indexing untuk performa pencarian dan filter
            $table->index(['course_id', 'created_at']);
            $table->index('is_solved');
        });

        // 2. Tabel Balasan Diskusi (Replies)
        Schema::create('discussion_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('discussion_id')->constrained('discussions')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade'); // Penjawab

            $table->text('body');
            $table->boolean('is_best_answer')->default(false); // Jawaban terpilih (Solution)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discussion_replies');
        Schema::dropIfExists('discussions');
    }
};
