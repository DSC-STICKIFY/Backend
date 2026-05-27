<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::dropIfExists('promotion_logs');

        Schema::create('promotion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_id');
            $table->unsignedBigInteger('sent_by');
            $table->timestamp('sent_at')->useCurrent();
            $table->unsignedBigInteger('total_recipients')->default(0);
            $table->unsignedBigInteger('successful_sends')->default(0);
            $table->unsignedBigInteger('failed_sends')->default(0);
            $table->json('failed_emails')->nullable();
            $table->timestamps();
        });

        // FK -> promotions.promotion_id
        DB::statement('ALTER TABLE promotion_logs
            ADD CONSTRAINT promotion_logs_promotion_id_foreign
            FOREIGN KEY (promotion_id) REFERENCES promotions(promotion_id)
            ON DELETE CASCADE');

    }

    public function down(): void {
        Schema::dropIfExists('promotion_logs');
    }
};
