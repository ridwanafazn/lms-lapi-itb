<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('level'); // 'starter', 'standard', 'pro'
            $table->string('billing_period'); // 'lifetime', 'monthly', 'quarterly', 'semiannual', 'yearly'
            $table->decimal('price', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Mencegah duplikasi paket yang sama
            $table->unique(['level', 'billing_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
