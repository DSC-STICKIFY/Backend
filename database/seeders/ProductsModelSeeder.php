<?php

namespace Database\Seeders;

use App\Models\ProductsModel;
use Illuminate\Database\Seeder;

class ProductsModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductsModel::factory()->count(5)->create();
    }
}
