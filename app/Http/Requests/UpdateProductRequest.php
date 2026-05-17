<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_name' => 'sometimes|string|max:255',
            'product_quantity' => 'sometimes|integer|min:0',
            'product_price' => 'sometimes|numeric|min:0',
            'product_category' => 'sometimes|string|max:255',
            'product_type' => 'sometimes|string|max:255',
            'product_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'product_description' => 'nullable|string',
            'is_car_service' => 'nullable|boolean',
            'is_motor_service' => 'nullable|boolean',
            'price_map_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'wrap_price' => 'nullable|numeric|min:0',
            'glossy_price' => 'nullable|numeric|min:0',
            'hologram_price' => 'nullable|numeric|min:0',
        ];
    }
}
