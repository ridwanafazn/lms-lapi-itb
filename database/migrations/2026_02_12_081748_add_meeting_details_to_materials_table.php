<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('meeting_id')->nullable()->after('zoom_url');
            $table->string('meeting_passcode')->nullable()->after('meeting_id');
            $table->string('meeting_platform')->default('zoom')->after('meeting_passcode')->comment('zoom, google_meet, teams, etc');
            $table->boolean('is_embeddable')->default(false)->after('meeting_platform');
        });

        // Sekalian menambahkan flag sequential pada course agar tidak perlu buat migrasi terpisah lagi
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_sequential')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['meeting_id', 'meeting_passcode', 'meeting_platform', 'is_embeddable']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('is_sequential');
        });
    }
};
