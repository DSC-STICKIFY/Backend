<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('returns_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_details_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('gcash_number')->nullable();
            $table->timestamps();

            $table->foreign('order_id')
                ->references('order_id')
                ->on('orders_table')
                ->onDelete('cascade');

            $table->foreign('order_details_id')
                ->references('order_details_id')
                ->on('orders_details_table')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users_table')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns_refunds');
    }
};