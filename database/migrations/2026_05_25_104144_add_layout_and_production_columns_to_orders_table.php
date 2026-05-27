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
        Schema::table('orders_table', function (Blueprint $table) {
            $table->timestamp('layout_submitted_at')->nullable();
            $table->timestamp('layout_approved_at')->nullable();
            $table->string('layout_rejection_reason', 500)->nullable();
            
            $table->timestamp('production_started_at')->nullable();
            $table->timestamp('production_completed_at')->nullable();
            $table->unsignedBigInteger('producer_staff_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders_table', function (Blueprint $table) {
            $table->dropColumn([
                'layout_submitted_at',
                'layout_approved_at',
                'layout_rejection_reason',
                'production_started_at',
                'production_completed_at',
                'producer_staff_id'
            ]);
        });
    }
};
