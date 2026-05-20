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
        Schema::table('returns_refunds', function (Blueprint $table) {
            $table->boolean('subadmin_authorized')->default(false)->after('gcash_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns_refunds', function (Blueprint $table) {
            $table->dropColumn('subadmin_authorized');
        });
    }
};
