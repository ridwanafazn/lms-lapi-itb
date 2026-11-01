<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.5 - Submission untuk kelas tanpa batch.
 *
 * `submissions.batch_id` sebelumnya NOT NULL, sehingga tugas pada course
 * mandiri (tanpa batch) tidak bisa disimpan sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->char('batch_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->char('batch_id', 36)->nullable(false)->change();
        });
    }
};
