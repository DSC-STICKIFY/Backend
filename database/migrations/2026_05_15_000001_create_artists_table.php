<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists_table', function (Blueprint $table) {
            $table->bigIncrements('artist_id');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('profile_image')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('address')->nullable();
            $table->string('role')->default('artist');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists_table');
    }
};
