<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 15, 2);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('corporate_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name');
            $table->foreignUuid('buyer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('program_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('seat_count');
            $table->decimal('unit_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('coupon_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->foreignUuid('corporate_purchase_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->decimal('discount_amount', 15, 2);
            $table->string('status', 20)->default('reserved');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            $table->index(['coupon_id', 'status']);
        });

        Schema::create('corporate_seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('corporate_purchase_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('allocated_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('enrollment_id')->constrained()->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->unique(['corporate_purchase_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_seats');
        Schema::dropIfExists('coupon_redemptions');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropConstrainedForeignId('corporate_purchase_id');
            $table->dropColumn(['original_amount', 'discount_amount']);
        });
        Schema::dropIfExists('corporate_purchases');
        Schema::dropIfExists('coupons');
    }
};
