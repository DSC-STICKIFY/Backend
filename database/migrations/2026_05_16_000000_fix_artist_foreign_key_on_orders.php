<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL, we can't easily drop by column if we don't know the name
        // But we can try the standard Laravel naming convention
        Schema::table('orders_table', function (Blueprint $table) {
            try {
                // Try standard name
                $table->dropForeign('orders_table_artist_id_foreign');
            } catch (\Exception $e) {
                // If that fails, try dropping by array (Laravel will guess the name)
                try {
                    $table->dropForeign(['artist_id']);
                } catch (\Exception $e2) {
                    // If both fail, it might not have a foreign key yet
                }
            }

            // Now add the correct one
            $table->foreign('artist_id')
                ->references('employee_id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders_table', function (Blueprint $table) {
            try {
                $table->dropForeign(['artist_id']);
            } catch (\Exception $e) {}
            
            $table->foreign('artist_id')
                ->references('artist_id')
                ->on('artists_table')
                ->nullOnDelete();
        });
    }
};
