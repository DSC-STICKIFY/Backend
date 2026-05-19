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
        Schema::table('products_table', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('product_id');
        });

        // Generate UUIDs for all existing products
        $products = \Illuminate\Support\Facades\DB::table('products_table')->get();
        foreach ($products as $product) {
            \Illuminate\Support\Facades\DB::table('products_table')
                ->where('product_id', $product->product_id)
                ->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products_table', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
