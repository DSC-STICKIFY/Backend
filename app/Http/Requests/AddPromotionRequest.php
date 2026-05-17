<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',

            'type' => 'required|in:discount,bundle,freebie,seasonal',

            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',

            'min_quantity' => 'nullable|integer|min:1',
            'min_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',

            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',

            'usage_limit' => 'nullable|integer|min:1',

            'status' => 'required|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Promotion name is required.',
            'type.required' => 'Promotion type is required.',
            'type.in' => 'Invalid promotion type.',

            'start_date.required' => 'Start date is required.',
            'end_date.required' => 'End date is required.',
            'end_date.after_or_equal' => 'End date must not be before start date.',

            'status.required' => 'Promotion status is required.',
        ];
    }
}
