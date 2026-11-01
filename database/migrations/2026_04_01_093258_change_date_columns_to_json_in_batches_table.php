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
        Schema::table('batches', function (Blueprint $table) {
            $table->json('new_registration_deadline')->nullable();
            $table->json('new_start_date')->nullable();
            $table->json('new_end_date')->nullable();
        });

        // 2. Pindahkan data lama dan ubah strukturnya menjadi JSON format: {"date": "YYYY-MM-DD"}
        DB::table('batches')->orderBy('id')->chunk(100, function ($batches) {
            foreach ($batches as $batch) {
                DB::table('batches')->where('id', $batch->id)->update([
                    'new_registration_deadline' => $batch->registration_deadline 
                        ? json_encode(['date' => $batch->registration_deadline]) : null,
                    'new_start_date' => $batch->start_date 
                        ? json_encode(['date' => $batch->start_date]) : null,
                    'new_end_date' => $batch->end_date 
                        ? json_encode(['date' => $batch->end_date]) : null,
                ]);
            }
        });

        // 3. Hapus kolom DATE yang lama
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['registration_deadline', 'start_date', 'end_date']);
        });

        // 4. Rename kolom JSON baru ke nama aslinya
        Schema::table('batches', function (Blueprint $table) {
            $table->renameColumn('new_registration_deadline', 'registration_deadline');
            $table->renameColumn('new_start_date', 'start_date');
            $table->renameColumn('new_end_date', 'end_date');
        });
    }

    public function down(): void
    {
        // Reverse process
        Schema::table('batches', function (Blueprint $table) {
            $table->date('old_registration_deadline')->nullable();
            $table->date('old_start_date')->nullable();
            $table->date('old_end_date')->nullable();
        });

        DB::table('batches')->orderBy('id')->chunk(100, function ($batches) {
            foreach ($batches as $batch) {
                // Ekstrak string tanggal dari JSON
                $regDate = $batch->registration_deadline ? json_decode($batch->registration_deadline, true)['date'] ?? null : null;
                $startDate = $batch->start_date ? json_decode($batch->start_date, true)['date'] ?? null : null;
                $endDate = $batch->end_date ? json_decode($batch->end_date, true)['date'] ?? null : null;

                DB::table('batches')->where('id', $batch->id)->update([
                    'old_registration_deadline' => $regDate,
                    'old_start_date' => $startDate,
                    'old_end_date' => $endDate,
                ]);
            }
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['registration_deadline', 'start_date', 'end_date']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->renameColumn('old_registration_deadline', 'registration_deadline');
            $table->renameColumn('old_start_date', 'start_date');
            $table->renameColumn('old_end_date', 'end_date');
        });
    }
};