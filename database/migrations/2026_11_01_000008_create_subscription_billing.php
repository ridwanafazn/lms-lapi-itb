<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Membership is a single state on User, so only one billing schedule may own it.
            $table->foreignUuid('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('membership_plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_period', 32);
            $table->string('status', 20)->default('active');
            $table->timestamp('next_billing_at')->nullable();
            $table->foreignUuid('source_transaction_id')->nullable()->constrained('transactions')->restrictOnDelete();
            $table->foreignUuid('pending_transaction_id')->nullable()->unique()->constrained('transactions')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_billing_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('subscription_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('membership_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('membership_plan_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('previous_plan_id')->nullable()->constrained('membership_plans')->restrictOnDelete();
            $table->timestamp('previous_expires_at')->nullable();
            $table->timestamp('granted_from');
            $table->timestamp('granted_until')->nullable();
            $table->unsignedBigInteger('grant_order');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'grant_order']);
            $table->index(['user_id', 'reversed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_grants');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });
        Schema::dropIfExists('subscriptions');
    }
};
