<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->index();
        });

        DB::table('quiz_attempts')
            ->where(function ($query) {
                $query->whereNotNull('end_time')->orWhereNotNull('score');
            })
            ->update(['submitted_at' => DB::raw('COALESCE(end_time, graded_at, updated_at)')]);

        DB::table('quiz_attempts')->whereNotNull('score')->whereNull('graded_at')
            ->update(['graded_at' => DB::raw('COALESCE(end_time, submitted_at, updated_at)')]);

        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->decimal('score', 8, 2)->nullable()->default(null)->change();
            $table->foreignUuid('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
        });

        // Legacy essay zeros were defaults, not manual grades. Finalized attempts stay unchanged.
        DB::table('quiz_answers')
            ->whereIn('question_id', DB::table('questions')->select('id')->where('question_type', 'essay'))
            ->whereIn('quiz_attempt_id', DB::table('quiz_attempts')->select('id')->whereNull('score'))
            ->update(['score' => null]);
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graded_by');
            $table->dropColumn('graded_at');
        });
        DB::table('quiz_answers')->whereNull('score')->update(['score' => 0]);
        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->decimal('score', 8, 2)->nullable(false)->default(0)->change();
        });
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
