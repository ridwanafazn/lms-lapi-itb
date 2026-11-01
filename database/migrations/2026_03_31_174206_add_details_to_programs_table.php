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
        Schema::table('programs', function (Blueprint $table) {
            $table->string('short_description')->nullable()->after('description');
            $table->json('faq')->nullable()->after('short_description');
            $table->json('what_is_included')->nullable()->after('faq');
            $table->string('enrollment_code')->unique()->nullable()->after('title');
            $table->string('pdf_brochure_url')->nullable()->after('is_active');
            $table->string('status')->default('draft')->after('pdf_brochure_url');
            $table->decimal('rating_avg', 3, 2)->default(0.00)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // Ini akan menghapus kolom-kolom di atas jika kamu menjalankan 'php artisan migrate:rollback'
            $table->dropColumn([
                'short_description',
                'faq',
                'what_is_included',
                'enrollment_code',
                'pdf_brochure_url',
                'status',
                'rating_avg',
            ]);
        });
    }
};
