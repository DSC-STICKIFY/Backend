<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customization_request_id')
                  ->constrained('customization_requests')
                  ->cascadeOnDelete();

            $table->decimal('material_cost', 10, 2)->default(0);
            $table->decimal('printing_cost', 10, 2)->default(0);
            $table->decimal('design_fee', 10, 2)->default(0);
            $table->decimal('additional_charges', 10, 2)->default(0);
            $table->text('additional_notes')->nullable();
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
