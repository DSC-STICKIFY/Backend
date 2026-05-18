<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_name' => 'required|string|max:255',
            'product_category' => 'required|string|max:255',
            'product_type' => 'required|string|max:255',
            'product_price' => 'nullable|numeric|min:0',
            'product_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'product_description' => 'nullable|string',
            'is_car_service' => 'nullable|boolean',
            'is_motor_service' => 'nullable|boolean',
            'price_map_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'wrap_price' => 'nullable|numeric|min:0',
            'glossy_price' => 'nullable|numeric|min:0',
            'hologram_price' => 'nullable|numeric|min:0',
            'is_customizable' => 'nullable|boolean',
        ];
    }
}
