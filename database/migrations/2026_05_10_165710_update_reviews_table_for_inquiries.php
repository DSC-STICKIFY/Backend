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
        Schema::table('reviews', function (Blueprint $table) {
            // Make order and product fields nullable
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->unsignedBigInteger('product_id')->nullable()->change();
            
            // Add inquiry_id
            $table->unsignedBigInteger('inquiry_id')->nullable()->after('product_id');
            $table->foreign('inquiry_id')->references('id')->on('inquiries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['inquiry_id']);
            $table->dropColumn('inquiry_id');
            
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
