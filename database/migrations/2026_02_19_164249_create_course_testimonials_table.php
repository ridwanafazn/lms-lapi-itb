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
        Schema::create('course_testimonials', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relasi ke User (Siswa) dan Course
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();

            // Metrik Penilaian (1-5 Bintang)
            $table->tinyInteger('rating_material');
            $table->tinyInteger('rating_instructor');
            $table->tinyInteger('rating_platform');

            // Ulasan
            $table->text('public_testimonial'); // Wajib, untuk ditampilkan di halaman publik
            $table->text('private_feedback')->nullable(); // Opsional, masukan tertutup untuk admin/instruktur

            // Status Moderasi
            $table->enum('status', ['published', 'hidden'])->default('published');

            $table->timestamps();

            // Mencegah user memberi testimoni berkali-kali pada satu course yang sama
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_testimonials');
    }
};
