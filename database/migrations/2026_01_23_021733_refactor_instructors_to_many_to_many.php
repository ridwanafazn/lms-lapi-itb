<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bersihkan tabel lama jika ada (Clean up)
        Schema::dropIfExists('course_instructors');
        Schema::dropIfExists('batch_instructors');
        Schema::dropIfExists('program_instructors');

        // 2. Hapus kolom legacy di courses/batches jika masih ada
        // (Kita lakukan check dulu agar tidak error jika sudah dihapus)
        if (Schema::hasColumn('courses', 'instructor_id')) {
            // Opsional: Anda bisa memilih untuk MEMPERTAHANKAN instructor_id sebagai "Creator"
            // Jika ingin dihapus, uncomment baris di bawah:
            // Schema::table('courses', function (Blueprint $table) { $table->dropForeign(['instructor_id']); $table->dropColumn('instructor_id'); });
        }

        if (Schema::hasColumn('batches', 'main_instructor_id')) {
            Schema::table('batches', function (Blueprint $table) {
                $table->dropForeign(['main_instructor_id']);
                $table->dropColumn('main_instructor_id');
            });
        }

        // 3. Buat Tabel Polimorfik Tunggal: 'instructors'
        if (! Schema::hasTable('instructors')) {
            Schema::create('instructors', function (Blueprint $table) {
                $table->uuid('id')->primary();

                // Relasi ke User (Pengajar)
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

                // KUNCI UTAMA: Kolom Polimorfik (instructor_type & instructor_id)
                // Ini akan menyimpan referensi ke Course, Batch, atau Program
                $table->uuidMorphs('instructor');

                $table->boolean('is_primary')->default(false);
                $table->string('role_label')->nullable(); // Contoh: "Dosen Tamu"

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Rollback: Hapus tabel baru, kembalikan tabel lama (jika perlu rollback)
        Schema::dropIfExists('instructors');

        // ... Logic untuk recreate tabel lama (opsional, dikosongkan agar simple)
    }
};
