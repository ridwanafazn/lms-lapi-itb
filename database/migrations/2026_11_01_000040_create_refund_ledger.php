<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->index();
        });
        Schema::create('enrollment_payment_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('enrollment_id')->constrained()->restrictOnDelete();
            $table->boolean('preserves_existing_access')->default(false);
            $table->string('previous_status', 20)->nullable();
            $table->timestamp('previous_access_expires_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'enrollment_id'], 'enrollment_payment_source_unique');
        });
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('processed_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('proof_path');
            $table->string('proof_sha256', 64);
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('enrollment_payment_sources');
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn('refunded_at'));
    }
};
