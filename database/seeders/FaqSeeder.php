<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\FaqModel::create([
            'question' => 'Where is your shop located?',
            'answer' => 'Our shop is located in Davao City. We are open from Monday to Saturday, 9:00 AM - 6:00 PM.'
        ]);

        \App\Models\FaqModel::create([
            'question' => 'What is the minimum order quantity?',
            'answer' => 'It depends on the product. For stickers, the minimum is usually 1 sheet. You can check the specific product details for more information.'
        ]);

        \App\Models\FaqModel::create([
            'question' => 'How can I track my order?',
            'answer' => 'You can track your order by visiting the "My Orders" section in your dashboard. You will see real-time updates on your order status.'
        ]);

        \App\Models\FaqModel::create([
            'question' => 'What printing services do you offer?',
            'answer' => 'We offer a wide range of services including Custom Stickers, Decals, Signages, and Graphic Design services.'
        ]);
    }
}
