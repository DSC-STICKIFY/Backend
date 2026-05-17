<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->bigIncrements('promotion_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->nullable();
            $table->enum('discount_type', ['fixed', 'percentage', 'free_shipping']);
            $table->decimal('discount_value', 10, 2);
            $table->integer('min_quantity')->nullable();
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('display_type', ['product', 'checkout'])->default('product');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
