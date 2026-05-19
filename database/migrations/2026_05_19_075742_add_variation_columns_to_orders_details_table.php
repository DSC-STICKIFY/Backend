<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_details_table', function (Blueprint $table) {
            $table->string('design_name')->nullable()->after('has_review');
            $table->string('quality_name')->nullable()->after('design_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders_details_table', function (Blueprint $table) {
            $table->dropColumn(['design_name', 'quality_name']);
        });
    }
};
