<?php

namespace Database\Seeders;

use App\Models\UserModel;
use Illuminate\Database\Seeder;

class UserModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UserModel::factory()->count(1)->create();
    }
}
