<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('question_image_url')->nullable()->after('question_text');
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->string('option_image_url')->nullable()->after('option_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_tables', function (Blueprint $table) {
            //
        });
    }
};
