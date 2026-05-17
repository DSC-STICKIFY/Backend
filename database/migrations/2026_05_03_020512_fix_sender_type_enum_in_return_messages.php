<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE return_messages MODIFY COLUMN sender_type ENUM('user', 'admin') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE return_messages MODIFY COLUMN sender_type ENUM('user_id', 'admin_id') NOT NULL");
    }
};