<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customization_requests', function (Blueprint $table) {
            $table->timestamp('in_progress_at')->nullable()->after('artist_id');
            $table->timestamp('expected_shipped_at')->nullable()->after('in_progress_at');
            $table->timestamp('expected_delivery_at')->nullable()->after('expected_shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('customization_requests', function (Blueprint $table) {
            $table->dropColumn(['in_progress_at', 'expected_shipped_at', 'expected_delivery_at']);
        });
    }
};
