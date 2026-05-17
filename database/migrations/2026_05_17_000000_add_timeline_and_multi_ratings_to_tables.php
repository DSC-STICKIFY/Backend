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
        // 1. Add expected shipped & expected delivery columns to orders_table
        Schema::table('orders_table', function (Blueprint $table) {
            $table->dateTime('expected_shipped_at')->nullable()->after('in_progress_at');
            $table->dateTime('expected_delivery_at')->nullable()->after('expected_shipped_at');
        });

        // 2. Add artist & rider rating/comment columns to reviews table
        Schema::table('reviews', function (Blueprint $table) {
            $table->tinyInteger('artist_rating')->unsigned()->nullable()->after('comment');
            $table->text('artist_comment')->nullable()->after('artist_rating');
            $table->tinyInteger('rider_rating')->unsigned()->nullable()->after('artist_comment');
            $table->text('rider_comment')->nullable()->after('rider_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders_table', function (Blueprint $table) {
            $table->dropColumn(['expected_shipped_at', 'expected_delivery_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['artist_rating', 'artist_comment', 'rider_rating', 'rider_comment']);
        });
    }
};
