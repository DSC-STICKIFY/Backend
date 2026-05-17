<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('return_media', function (Blueprint $table) {
            $table->id();
           $table->foreignId('return_id')
            ->constrained('returns_refunds')
            ->onDelete('cascade');
            $table->string('file_path');
            $table->enum('file_type', ['image', 'video']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('return_media');
    }
};