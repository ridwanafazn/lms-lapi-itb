<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // 1. Tambahkan kolom course_id
            $table->foreignUuid('course_id')->nullable()->after('user_id')->constrained('courses')->cascadeOnDelete();

            // 2. Ubah batch_id menjadi nullable (boleh kosong)
            $table->char('batch_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
            $table->char('batch_id', 36)->nullable(false)->change();
        });
    }
};
