<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products_table', function (Blueprint $table) {
            $table->string('shelf_location')->nullable()->after('is_customizable');
        });
    }

    public function down(): void
    {
        Schema::table('products_table', function (Blueprint $table) {
            $table->dropColumn('shelf_location');
        });
    }
};
