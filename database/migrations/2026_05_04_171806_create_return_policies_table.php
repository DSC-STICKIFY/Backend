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
        Schema::create('return_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('allowed_value');
            $table->enum('allowed_unit', ['minutes', 'hours', 'days']);
            $table->enum('scope_type', ['all', 'category', 'type', 'product']);
            
            $table->string('category_name')->nullable();
            $table->string('type_name')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            
            $table->boolean('is_returnable')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_policies');
    }
};
