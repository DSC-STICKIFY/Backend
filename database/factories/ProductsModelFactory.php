<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductsModel>
 */
class ProductsModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['Stickers', 'Signage', 'Giveaways', 'Decal'];
        $category = $this->faker->randomElement($categories);
        
        $types = [
            'Stickers' => ['Assorted Hologram', 'Vinyl', 'Paper'],
            'Signage' => ['Acrylic Signage', 'Panaflex Signage', 'Neon Lights'],
            'Giveaways' => ['Mug', 'Keychain', 'T-Shirt'],
            'Decal' => ['Motorcycle', 'Car', 'Window']
        ];

        return [
            'product_name' => $this->faker->word() . ' ' . $category,
            'product_description' => $this->faker->paragraph(),
            'product_category' => $category,
            'product_type' => $this->faker->randomElement($types[$category]),
            'product_quantity' => $this->faker->numberBetween(1, 100),
            'product_price' => $this->faker->randomFloat(2, 1, 1000),
            'product_image' => null,
            'slug' => $this->faker->unique()->slug(),
        ];
    }
}
