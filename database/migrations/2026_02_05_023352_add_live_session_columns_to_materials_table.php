<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('zoom_url')->nullable()->after('content_url');
            $table->dateTime('start_time')->nullable()->after('zoom_url');
            $table->integer('duration')->nullable()->comment('Durasi dalam menit')->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['zoom_url', 'start_time', 'duration']);
        });
    }
};
