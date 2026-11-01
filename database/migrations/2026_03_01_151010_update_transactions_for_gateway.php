<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('gateway_name')->nullable()->after('payment_method');
            $table->string('gateway_reference')->nullable()->after('gateway_name');
            $table->string('snap_token')->nullable()->after('gateway_reference');
            $table->string('redirect_url')->nullable()->after('snap_token');
            $table->json('gateway_payload')->nullable()->after('redirect_url');
            $table->timestamp('paid_at')->nullable()->after('transaction_date');
            $table->timestamp('expired_at')->nullable()->after('paid_at');
            $table->timestamp('settlement_time')->nullable()->after('expired_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_name',
                'gateway_reference',
                'snap_token',
                'redirect_url',
                'gateway_payload',
                'paid_at',
                'expired_at',
                'settlement_time'
            ]);
        });
    }
};
