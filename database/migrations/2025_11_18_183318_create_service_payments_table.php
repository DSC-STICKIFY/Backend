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
        Schema::create('service_payments', function (Blueprint $table) {
            $table->bigIncrements('payment_id');
            $table->foreignId('service_id')->constrained('services', 'services_id')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees', 'employee_id');
            $table->foreignId('product_id')->constrained('products_table', 'product_id');
            $table->decimal('payment_amount', 10, 2);
            $table->string('customer');
            $table->date('payment_date');
            $table->string('invoice', 255)->nullable();

            $table->timestamps();

            // Foreign Keys

        });
    }
};
