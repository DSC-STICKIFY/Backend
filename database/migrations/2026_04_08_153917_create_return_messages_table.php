<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('return_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_id');
            $table->enum('sender_type', ['user_id', 'admin_id']);
            $table->unsignedBigInteger('sender_id');
            $table->text('message');
            $table->timestamps();


            $table->index(['return_id', 'created_at']);
        });

        
        Schema::table('return_messages', function (Blueprint $table) {
            $table->foreign('return_id')
                ->references('id')
                ->on('returns_refunds')   
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('return_messages');
    }
};