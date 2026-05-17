<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',

            'type' => 'sometimes|required|in:discount,bundle,freebie,seasonal',

            'discount_type' => 'sometimes|nullable|in:percentage,fixed',
            'discount_value' => 'sometimes|nullable|numeric|min:0',

            'min_quantity' => 'sometimes|nullable|integer|min:1',
            'min_amount' => 'sometimes|nullable|numeric|min:0',
            'max_discount' => 'sometimes|nullable|numeric|min:0',

            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',

            'usage_limit' => 'sometimes|nullable|integer|min:1',

            'status' => 'sometimes|required|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Promotion name cannot be empty.',
            'type.required' => 'Promotion type is required.',
            'type.in' => 'Invalid promotion type.',

            'end_date.after_or_equal' => 'End date must not be before start date.',
        ];
    }
}
