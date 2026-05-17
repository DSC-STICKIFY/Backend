<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders_table', function (Blueprint $table) {
            $table->bigIncrements('order_id');
            $table->foreignId('user_id')
                ->constrained('users_table', 'user_id')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->foreign('promotion_id')
                ->references('promotion_id')
                ->on('promotions')
                ->nullOnDelete();

            $table->string('order_number')->unique()->nullable();
            $table->string('courier')->default('J&T');
            $table->date('order_date');
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->string('status')->default('Pending');
            $table->boolean('has_review')->default(false);
            $table->string('payment_method')->default('COD');
            $table->string('paymongo_source_id')->nullable()->index();
            $table->string('contact_number')->nullable();
            $table->string('address')->nullable();
            $table->string('delivery_address')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('return_reason')->nullable();
            $table->text('return_details')->nullable();

            // Shipping & tracking columns
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivery_deadline')->nullable();
            $table->timestamp('auto_completed_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_table');
    }
};