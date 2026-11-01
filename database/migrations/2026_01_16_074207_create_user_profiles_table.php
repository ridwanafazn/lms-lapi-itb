<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();

            $table->string('full_name')->nullable();
            $table->string('nik')->nullable()->unique();
            $table->string('phone_number')->nullable();

            $table->string('agency_name')->nullable();
            $table->string('position')->nullable();

            $table->text('address')->nullable();
            $table->string('profile_photo_url')->nullable();

            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
