<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.4 (lanjutan) - Kolom yang dipakai model Batch tetapi tidak ada.
 *
 * `Batch::$fillable`, `getEffectivePriceAttribute()` dan `isFreeForUser()`
 * membaca `price` dan `min_membership_level`, sementara tabel `batches`
 * tidak pernah memiliki keduanya. Akibatnya:
 * - harga batch selalu jatuh ke harga program (tidak bisa dioverride),
 * - membership tidak pernah membuka batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->default(0)->after('batch_name');
            $table->enum('min_membership_level', ['none', 'standard', 'pro'])
                ->default('none')
                ->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['price', 'min_membership_level']);
        });
    }
};
