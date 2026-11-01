<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat kolom JSON sementara
        Schema::table('courses', function (Blueprint $table) {
            $table->json('new_target_audience')->nullable();
            $table->json('new_prerequisites')->nullable();
        });

        // 2. Pindahkan data lama dan pecah teks berdasar koma menjadi JSON Array
        DB::table('courses')->orderBy('id')->chunk(100, function ($courses) {
            foreach ($courses as $course) {
                // Pecah string berdasarkan koma, lalu bersihkan spasi ekstra (trim)
                $targets = $course->target_audience 
                    ? array_filter(array_map('trim', explode(',', $course->target_audience))) 
                    : null;
                
                $prereqs = $course->prerequisites 
                    ? array_filter(array_map('trim', explode(',', $course->prerequisites))) 
                    : null;

                DB::table('courses')->where('id', $course->id)->update([
                    'new_target_audience' => $targets ? json_encode(array_values($targets)) : null,
                    'new_prerequisites' => $prereqs ? json_encode(array_values($prereqs)) : null,
                ]);
            }
        });

        // 3. Hapus kolom TEXT yang lama
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['target_audience', 'prerequisites']);
        });

        // 4. Rename kolom JSON baru ke nama aslinya
        Schema::table('courses', function (Blueprint $table) {
            $table->renameColumn('new_target_audience', 'target_audience');
            $table->renameColumn('new_prerequisites', 'prerequisites');
        });
    }

    public function down(): void
    {
        // Reverse process
        Schema::table('courses', function (Blueprint $table) {
            $table->text('old_target_audience')->nullable();
            $table->text('old_prerequisites')->nullable();
        });

        DB::table('courses')->orderBy('id')->chunk(100, function ($courses) {
            foreach ($courses as $course) {
                // Ekstrak array JSON kembali menjadi string yang dipisah koma
                $targetsArr = $course->target_audience ? json_decode($course->target_audience, true) : [];
                $prereqsArr = $course->prerequisites ? json_decode($course->prerequisites, true) : [];

                DB::table('courses')->where('id', $course->id)->update([
                    'old_target_audience' => !empty($targetsArr) ? implode(', ', $targetsArr) : null,
                    'old_prerequisites' => !empty($prereqsArr) ? implode(', ', $prereqsArr) : null,
                ]);
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['target_audience', 'prerequisites']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->renameColumn('old_target_audience', 'target_audience');
            $table->renameColumn('old_prerequisites', 'prerequisites');
        });
    }
};