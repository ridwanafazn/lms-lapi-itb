<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign('enrollments_user_id_foreign');
            $table->dropUnique(['user_id', 'batch_id']);
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
            $table->uuidMorphs('enrollable');
            $table->unique(['user_id', 'enrollable_type', 'enrollable_id'], 'user_enrollment_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('user_enrollment_unique');
            $table->dropMorphs('enrollable');

            $table->char('batch_id', 36)->after('user_id');
            $table->unique(['user_id', 'batch_id']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
