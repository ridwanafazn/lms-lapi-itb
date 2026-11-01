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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Full UUID

            $table->uuid('actor_id')->nullable(); // Konsisten UUID
            $table->string('actor_name')->nullable();
            $table->string('action'); // CREATE, UPDATE, DELETE
            $table->text('description');
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index('actor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
