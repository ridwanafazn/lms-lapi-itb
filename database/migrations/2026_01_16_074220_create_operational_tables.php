<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. BATCHES (Angkatan)
        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained()->cascadeOnDelete();
            $table->string('batch_name');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->foreignUuid('main_instructor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('whatsapp_group_link')->nullable();
            $table->enum('status', ['open', 'ongoing', 'closed'])->default('open');
            $table->timestamps();
        });

        // 2. ENROLLMENTS
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained()->cascadeOnDelete();

            $table->timestamp('enrolled_at');
            $table->timestamp('access_expires_at')->nullable();

            $table->enum('status', ['active', 'completed', 'dropped'])->default('active');
            $table->double('progress_percentage')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'batch_id']);
        });

        // 3. TRANSACTIONS
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_code')->unique(); // INV-20260101-001
            $table->foreignUuid('user_id')->constrained();

            $table->foreignUuid('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['manual_transfer', 'gateway_va', 'gateway_ewallet'])->default('manual_transfer');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'verified_by_admin'])->default('pending');

            $table->string('proof_file_url')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();
        });

        // 4. SUBMISSIONS
        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->constrained();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('batch_id')->constrained();

            $table->string('file_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->useCurrent();

            $table->float('grade')->nullable();
            $table->text('feedback')->nullable();

            $table->timestamps();
        });

        // 5. QUIZ ATTEMPTS
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quiz_id')->constrained();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('batch_id')->constrained();

            $table->float('score')->default(0);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();

            $table->timestamps();
        });

        // 6. CERTIFICATES
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('batch_id')->constrained();

            $table->string('certificate_code')->unique();
            $table->date('issued_at');
            $table->string('file_url');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('batches');
    }
};
