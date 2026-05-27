<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inquiry_id');
            $table->string('sender_type'); // 'user', 'admin', 'subadmin', 'staff', 'customer_service'
            $table->unsignedBigInteger('sender_id');
            $table->text('message');
            $table->timestamps();

            $table->index(['inquiry_id', 'created_at']);
            
            $table->foreign('inquiry_id')
                ->references('id')
                ->on('inquiries')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inquiry_messages');
    }
};
