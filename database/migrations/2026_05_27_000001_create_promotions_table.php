<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // The promotions table already exists (from 2025_11_10_161700)
        // PK = promotion_id, users table = users_table with PK = user_id
        // We just add the new workflow columns.

        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'title')) {
                $table->string('title')->nullable()->after('promotion_id');
            }
            if (!Schema::hasColumn('promotions', 'target_type')) {
                $table->enum('target_type', [
                    'all_verified',
                    'recent_buyers',
                    'custom_order_customers',
                    'inactive_customers',
                ])->default('all_verified')->after('status');
            }
            if (!Schema::hasColumn('promotions', 'banner_image')) {
                $table->string('banner_image')->nullable()->after('target_type');
            }
            if (!Schema::hasColumn('promotions', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('banner_image');
            }
            if (!Schema::hasColumn('promotions', 'promo_code')) {
                $table->string('promo_code')->nullable()->after('expiration_date');
            }
            if (!Schema::hasColumn('promotions', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('promo_code');
            }
        });

        // Add FK for created_by -> users_table.user_id (only if column was just added)
        if (Schema::hasColumn('promotions', 'created_by')) {
            // Check if FK already exists
            $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promotions'
                AND CONSTRAINT_NAME = 'promotions_created_by_foreign'");
            if (empty($fks)) {
                DB::statement('ALTER TABLE promotions
                    ADD CONSTRAINT promotions_created_by_foreign
                    FOREIGN KEY (created_by) REFERENCES users_table(user_id)
                    ON DELETE SET NULL');
            }
        }

        // Expand the status enum to include workflow statuses
        DB::statement("ALTER TABLE promotions MODIFY COLUMN status ENUM(
            'active','inactive',
            'draft','pending_review','ready_to_send','sent','expired','cancelled'
        ) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $cols = ['title', 'target_type', 'banner_image', 'expiration_date', 'promo_code', 'created_by'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('promotions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
