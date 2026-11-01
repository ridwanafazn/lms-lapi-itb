<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('instructor_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->string('evidence_sha256', 64)->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'instructor_user_id']);
            $table->index(['instructor_user_id', 'status']);
        });
        Schema::create('payout_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payout_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->json('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_audits');
        Schema::dropIfExists('payouts');
    }
};
