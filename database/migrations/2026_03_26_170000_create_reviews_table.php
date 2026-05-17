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
        Schema::create('reviews', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();

            // Link to order (required)
            $table->foreignId('order_id')
                  ->constrained('orders_table', 'order_id')
                  ->onDelete('cascade');

            // Link to specific order item (optional but unique – one review per item)
            $table->unsignedBigInteger('order_details_id')->nullable()->unique();
            $table->foreign('order_details_id')
                  ->references('order_details_id')
                  ->on('orders_details_table')
                  ->onDelete('cascade');

            // User who wrote the review
            $table->foreignId('user_id')
                  ->constrained('users_table', 'user_id')
                  ->onDelete('cascade');

            // Product being reviewed
            $table->foreignId('product_id')
                  ->constrained('products_table', 'product_id')
                  ->onDelete('cascade');

            $table->tinyInteger('rating')->unsigned();
            $table->text('comment')->nullable();
            $table->text('admin_reply')->nullable();
            $table->enum('status', ['visible', 'hidden', 'pending'])->default('pending');

            $table->timestamps();

            // Indexes for performance
            $table->index('order_id');
            $table->index('product_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};