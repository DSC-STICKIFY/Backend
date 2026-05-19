<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_designs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('design_name');
            $table->string('design_image')->nullable();
            $table->decimal('additional_price', 10, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('product_id')->references('product_id')->on('products_table')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_designs');
    }
};
