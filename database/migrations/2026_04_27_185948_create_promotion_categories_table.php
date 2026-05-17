<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('promotion_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promotion_id')
                ->constrained('promotions', 'promotion_id')
                ->onDelete('cascade');

            // ❌ NO categories table, so NO foreign key constraint
            $table->string('category_name');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotion_categories');
    }
};