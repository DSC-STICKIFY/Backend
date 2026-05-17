<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('promotion_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promotion_id')
                ->constrained('promotions', 'promotion_id')
                ->onDelete('cascade');

            // FIXED: matches products_table + product_id
            $table->unsignedBigInteger('product_id');

            $table->foreign('product_id')
                ->references('product_id')
                ->on('products_table')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotion_products');
    }
};