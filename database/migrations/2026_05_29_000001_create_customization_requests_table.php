<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customization_requests', function (Blueprint $table) {
            $table->id();

            // Customer
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('user_id')->on('users_table')->cascadeOnDelete();

            // Product reference
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('product_id')->on('products_table')->nullOnDelete();

            // Customer submission details
            $table->string('product_name')->nullable();        // e.g. "Custom Stickers"
            $table->integer('quantity')->default(1);
            $table->string('material_type')->nullable();       // Matte, Glossy, Holographic, etc.
            $table->string('size_requested')->nullable();      // e.g. "3x3", "5x5"
            $table->text('instructions')->nullable();          // Free-text notes
            $table->string('reference_image')->nullable();     // Uploaded design path

            // Staff validation
            $table->string('validation_status')->default('pending');
            // pending | can_accommodate | partially_accommodate | cannot_accommodate
            $table->text('validation_notes')->nullable();
            $table->integer('approved_quantity')->nullable();

            // Artist assignment (after quotation approval)
            $table->unsignedBigInteger('artist_id')->nullable();
            $table->foreign('artist_id')->references('employee_id')->on('employees')->nullOnDelete();

            // Design mockup
            $table->string('mockup_image')->nullable();
            $table->string('design_status')->default('pending');
            // pending | designing | waiting_approval | revision_requested | approved

            // Overall workflow status
            $table->string('status')->default('pending_request');
            // pending_request | under_review | under_validation
            // quotation_sent | quotation_approved
            // assigned_to_artist | designing | waiting_customer_approval | revision_requested | approved_final_design
            // paid | printing | ready_for_shipment | shipped | delivered | completed | cancelled

            // Quotation total (denormalized for quick access)
            $table->decimal('quotation_total', 12, 2)->nullable();

            // Link to orders_table once converted
            $table->unsignedBigInteger('order_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customization_requests');
    }
};
