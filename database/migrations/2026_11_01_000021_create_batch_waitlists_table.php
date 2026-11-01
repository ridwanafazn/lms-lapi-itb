<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_waitlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('queue_number');
            $table->string('status', 20)->default('waiting');
            $table->timestamp('joined_at');
            $table->timestamp('promoted_at')->nullable();
            $table->foreignUuid('promoted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'user_id']);
            $table->unique(['batch_id', 'queue_number']);
            $table->index(['batch_id', 'status', 'queue_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_waitlists');
    }
};
