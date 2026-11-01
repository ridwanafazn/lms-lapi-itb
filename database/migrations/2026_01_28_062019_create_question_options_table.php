<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat Tabel Opsi Jawaban
        Schema::create('question_options', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relasi ke tabel questions
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();

            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->integer('order_index')->default(0);

            $table->timestamps();
        });

        // 2. Hapus kolom JSON 'options' dari tabel questions (karena sudah diganti tabel di atas)
        if (Schema::hasColumn('questions', 'options')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropColumn('options');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');

        if (Schema::hasTable('questions')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->json('options')->nullable();
            });
        }
    }
};
