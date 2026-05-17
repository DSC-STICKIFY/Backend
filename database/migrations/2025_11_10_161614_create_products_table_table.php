<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products_table', function (Blueprint $table) {
            $table->bigIncrements('product_id');
            $table->string('product_name');
            $table->text('product_description')->nullable();
            $table->integer('product_quantity')->default(0);
            $table->string('product_category')->nullable();
            $table->string('product_type')->nullable();
            $table->decimal('product_price', 8, 2);
            $table->decimal('wrap_price', 10, 2)->nullable();
            $table->decimal('glossy_price', 10, 2)->nullable();
            $table->decimal('hologram_price', 10, 2)->nullable();
            $table->boolean('is_car_service')->default(false);
            $table->boolean('is_motor_service')->default(false);
            $table->string('product_image')->nullable();
            $table->string('price_map_image')->nullable();
            $table->string('slug')->unique()->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products_table');
    }
};
