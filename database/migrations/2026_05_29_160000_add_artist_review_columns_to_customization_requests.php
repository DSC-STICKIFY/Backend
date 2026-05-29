<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customization_requests', function (Blueprint $table) {
            // Revision period tracking
            $table->boolean('needs_revision_period')->default(false)->after('design_status');
            $table->dateTime('revision_deadline')->nullable()->after('needs_revision_period');
            $table->integer('revision_count')->default(0)->after('revision_deadline');

            // Production scheduling set by Artist
            $table->date('production_date')->nullable()->after('revision_count');

            // Admin design review
            $table->text('admin_design_notes')->nullable()->after('production_date');

            // Quality Control tracking
            $table->string('qc_status')->default('pending')->after('admin_design_notes'); // pending | passed | failed
            $table->text('qc_notes')->nullable()->after('qc_status');

            // General CS/Staff communication notes
            $table->text('cs_notes')->nullable()->after('qc_notes');
        });
    }

    public function down(): void
    {
        Schema::table('customization_requests', function (Blueprint $table) {
            $table->dropColumn([
                'needs_revision_period',
                'revision_deadline',
                'revision_count',
                'production_date',
                'admin_design_notes',
                'qc_status',
                'qc_notes',
                'cs_notes',
            ]);
        });
    }
};
