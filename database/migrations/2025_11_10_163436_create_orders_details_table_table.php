<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders_details_table', function (Blueprint $table) {
            $table->bigIncrements('order_details_id');
            $table->foreignId('order_id')
                ->constrained('orders_table', 'order_id')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products_table', 'product_id')
                ->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('size')->nullable();
            $table->text('comments')->nullable();
            $table->decimal('item_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->string('status')->nullable()->default(null);
            $table->boolean('has_review')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders_details_table');
    }
};