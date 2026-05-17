<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_table', function (Blueprint $table) {
            // Artist assignment
            $table->unsignedBigInteger('artist_id')->nullable()->after('user_id');
            $table->foreign('artist_id')
                ->on('employees')
                ->references('employee_id')
                ->nullOnDelete();

            // Workflow timestamps & fields
            $table->timestamp('in_progress_at')->nullable()->after('artist_id');
            $table->string('final_design_url')->nullable()->after('in_progress_at');
            $table->timestamp('customer_approved_at')->nullable()->after('final_design_url');
            $table->timestamp('shipment_requested_at')->nullable()->after('customer_approved_at');
            $table->text('shipment_note')->nullable()->after('shipment_requested_at');

            // Cancel fields (replaces generic return_reason for cancelled orders)
            $table->text('cancel_reason')->nullable()->after('shipment_note');
            $table->string('refund_status')->nullable()->after('cancel_reason');
            // refund_status values: null | 'Refund Initiated' | 'Refunded' | 'No Refund (COD)'
        });
    }

    public function down(): void
    {
        Schema::table('orders_table', function (Blueprint $table) {
            $table->dropForeign(['artist_id']);
            $table->dropColumn([
                'artist_id',
                'in_progress_at',
                'final_design_url',
                'customer_approved_at',
                'shipment_requested_at',
                'shipment_note',
                'cancel_reason',
                'refund_status',
            ]);
        });
    }
};
