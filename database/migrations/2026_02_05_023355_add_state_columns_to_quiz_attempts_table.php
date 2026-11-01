<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->integer('current_question_index')->default(0)->after('score'); // Menyimpan posisi nomor soal terakhir
            $table->integer('remaining_seconds')->nullable()->after('current_question_index'); // Menyimpan sisa waktu server-side
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn(['current_question_index', 'remaining_seconds']);
        });
    }
};
