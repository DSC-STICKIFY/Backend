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
            $table->string('refund_proof')->nullable()->after('gcash_number');
            $table->timestamp('refund_completed_at')->nullable()->after('refund_proof');
            $table->string('paymongo_refund_id')->nullable()->after('refund_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns_refunds', function (Blueprint $table) {
            $table->dropColumn(['refund_proof', 'refund_completed_at', 'paymongo_refund_id']);
        });
    }
};
