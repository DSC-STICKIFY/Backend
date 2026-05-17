<?php

namespace Database\Seeders;

use App\Models\AdminModel;
use Illuminate\Database\Seeder;

class AdminModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AdminModel::factory()->count(1)->create();
    }
}
