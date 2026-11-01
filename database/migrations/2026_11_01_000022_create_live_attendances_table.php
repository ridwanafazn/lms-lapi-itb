<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('material_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['batch_id', 'material_id', 'user_id'], 'live_attendance_session_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_attendances');
    }
};
