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
        Schema::create('orders_payments_table', function (Blueprint $table) {
            $table->bigIncrements('payment_id');
            $table->foreignId('order_id')->constrained('orders_table', 'order_id')->cascadeOnDelete();
            $table->double('payment_amount');
            $table->double('amount_paid');
            $table->date('payment_date');
            $table->string('reference_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders_payments_table');
    }
};
