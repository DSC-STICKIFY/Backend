<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_table', function (Blueprint $table) {
            $table->string('cs_review_status')->default('pending_review')->after('status');
            $table->string('staff_validation_status')->default('pending_validation')->after('cs_review_status');
            $table->integer('manual_approved_quantity')->nullable()->after('staff_validation_status');
            $table->text('staff_validation_note')->nullable()->after('manual_approved_quantity');
            $table->text('rejection_reason')->nullable()->after('staff_validation_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders_table', function (Blueprint $table) {
            $table->dropColumn([
                'cs_review_status',
                'staff_validation_status',
                'manual_approved_quantity',
                'staff_validation_note',
                'rejection_reason',
            ]);
        });
    }
};
