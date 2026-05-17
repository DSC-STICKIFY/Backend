<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminModel;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        AdminModel::updateOrCreate(
            ['email' => 'admin@example.com'], // Prevent duplicates
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'address' => '123 Tech Street',
                'contact_number' => '09123456789',
                'password' => Hash::make('admin123'), // Mock password
            ]
        );
    }
}
