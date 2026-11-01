<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('membership_plan_id', 36)->nullable()->after('remember_token');
            $table->timestamp('membership_expires_at')->nullable()->after('membership_plan_id');

            $table->foreign('membership_plan_id')
                ->references('id')
                ->on('membership_plans')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['membership_plan_id']);
            $table->dropColumn(['membership_plan_id', 'membership_expires_at']);
        });
    }
};
