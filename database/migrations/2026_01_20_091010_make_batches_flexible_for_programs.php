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
        Schema::table('batches', function (Blueprint $table) {
            // 1. Hapus Foreign Key lama pada course_id (wajib didrop dulu sebelum diubah)
            $table->dropForeign(['course_id']);

            // 2. Ubah course_id menjadi Nullable (Boleh Kosong)
            // Penting: Pastikan tipe data 'char' dan length '36' sama persis dengan sebelumnya
            $table->char('course_id', 36)->nullable()->change();

            // 3. Tambahkan kolom program_id (Nullable) setelah id
            $table->char('program_id', 36)->nullable()->after('id');

            // 4. Tambahkan Relasi Foreign Key untuk program_id
            $table->foreign('program_id')
                  ->references('id')
                  ->on('programs')
                  ->onDelete('cascade'); // Jika program dihapus, batch ikut terhapus

            // 5. Pasang kembali Foreign Key untuk course_id (yang sekarang nullable)
            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Rollback: Hapus program_id
            $table->dropForeign(['program_id']);
            $table->dropColumn('program_id');

            // Rollback: Kembalikan course_id menjadi TIDAK Nullable
            // Kita drop dulu FK-nya
            $table->dropForeign(['course_id']);

            // Ubah jadi not null (default lama)
            $table->char('course_id', 36)->nullable(false)->change();

            // Pasang lagi FK lama
            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');
        });
    }
};
