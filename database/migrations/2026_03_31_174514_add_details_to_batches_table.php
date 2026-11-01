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
            $table->date('registration_deadline')->nullable()->after('batch_name');
            $table->integer('quota')->default(0)->after('end_date');
            $table->text('location')->nullable()->after('quota'); // Alamat fisik atau link zoom
            $table->json('timeline_detail')->nullable()->after('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn([
                'registration_deadline',
                'quota',
                'location',
                'timeline_detail'
            ]);
        });
    }
};