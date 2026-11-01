<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.9 - Soft delete untuk entitas inti.
 *
 * Menghapus course/program/batch/user tidak lagi menghilangkan data dan
 * memutus relasi (transaksi, sertifikat, enrollment tetap terbaca).
 */
return new class extends Migration
{
    private array $tables = ['courses', 'programs', 'batches', 'users'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
