<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Kita mengubah definisi kolom payment_method untuk menambahkan 'free_access'
        // Pastikan urutan dan nilai lama tetap ada agar data lama tidak rusak.
        DB::statement("
            ALTER TABLE transactions
            MODIFY COLUMN payment_method
            ENUM('manual_transfer', 'gateway_va', 'gateway_ewallet', 'free_access')
            NOT NULL DEFAULT 'manual_transfer'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke kondisi semula (menghapus opsi 'free_access')
        // PERINGATAN: Data dengan 'free_access' akan menjadi error/kosong jika ini dijalankan.
        DB::statement("
            ALTER TABLE transactions
            MODIFY COLUMN payment_method
            ENUM('manual_transfer', 'gateway_va', 'gateway_ewallet')
            NOT NULL DEFAULT 'manual_transfer'
        ");
    }
};
