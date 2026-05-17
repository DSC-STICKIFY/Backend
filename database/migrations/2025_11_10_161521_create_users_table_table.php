<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users_table', function (Blueprint $table) {
            $table->bigIncrements('user_id');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('username')->unique();
            $table->string('date_of_birth');
            $table->string('address');
            $table->string('contact_number');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('profile_image')->nullable();
            $table->timestamp('last_verification_sent_at')->nullable();
            $table->boolean('receive_promotional_emails')->default(true);
            $table->string('password');
            $table->string('role')->default('user');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_table');
    }
};
