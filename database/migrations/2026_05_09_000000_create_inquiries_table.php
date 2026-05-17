<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();

            // Customer tracking
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users_table')->onDelete('set null');

            $table->string('service_type'); // car_wrap, car_decal, motor, etc.
            $table->string('customer_name');
            $table->string('contact_number');
            $table->string('email');
            $table->string('address')->nullable();

            // Vehicle fields
            $table->string('car_type')->nullable();
            $table->string('motor_model')->nullable();

            // Wrap / Decal fields
            $table->string('wrap_type')->nullable(); // Full, Partial, etc.
            $table->string('finish_type')->nullable();
            $table->string('decal_type')->nullable();
            $table->string('placement')->nullable();
            $table->string('size')->nullable();
            $table->string('color_style')->nullable(); // Preferred Color/Style

            $table->string('image')->nullable();
            $table->text('message')->nullable();

            // Admin response
            $table->text('admin_message')->nullable();
            $table->decimal('quotation_amount', 12, 2)->nullable();
            $table->decimal('downpayment_amount', 12, 2)->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);

            // Scheduling
            $table->dateTime('schedule_date')->nullable();

            // Status
            $table->string('status')->default('pending'); // pending, reviewed, quoted, approved, completed
            $table->text('rejection_reason')->nullable();

            // Payment
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('payment_intent_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
