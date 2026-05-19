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
        Schema::table('users_table', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('user_id');
        });

        // Generate UUIDs for all existing users
        $users = \Illuminate\Support\Facades\DB::table('users_table')->get();
        foreach ($users as $user) {
            \Illuminate\Support\Facades\DB::table('users_table')
                ->where('user_id', $user->user_id)
                ->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_table', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
