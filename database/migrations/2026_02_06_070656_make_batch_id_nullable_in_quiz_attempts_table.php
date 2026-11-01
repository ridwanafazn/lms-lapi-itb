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
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign('quiz_attempts_batch_id_foreign');

            $table->string('batch_id')->nullable()->change();

            $table->foreign('batch_id')
                  ->references('id')
                  ->on('batches')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->string('batch_id')->nullable(false)->change();

            $table->foreign('batch_id')
                  ->references('id')
                  ->on('batches')
                  ->cascadeOnDelete();
        });
    }
};
