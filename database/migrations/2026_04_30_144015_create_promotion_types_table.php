<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
                Schema::create('promotion_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('promotion_id');
                $table->string('type_name');
                $table->timestamps();

                $table->foreign('promotion_id')
                    ->references('promotion_id')  // ← 'promotion_id' not 'id'
                    ->on('promotions')
                    ->onDelete('cascade');
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_types');
    }
};