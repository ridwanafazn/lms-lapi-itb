<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.4 - Kolom gating membership untuk Program.
 *
 * Model Program sudah memakai kedua kolom ini (isFreeForUser,
 * requiredMembershipPlan, User::getAccessibleProgramsAttribute) tetapi
 * migrasinya tidak pernah dibuat sehingga query gagal di runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->enum('min_membership_level', ['none', 'standard', 'pro'])
                ->default('none')
                ->after('type');

            $table->char('required_membership_plan_id', 36)
                ->nullable()
                ->after('min_membership_level');

            $table->foreign('required_membership_plan_id')
                ->references('id')
                ->on('membership_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['required_membership_plan_id']);
            $table->dropColumn(['min_membership_level', 'required_membership_plan_id']);
        });
    }
};
