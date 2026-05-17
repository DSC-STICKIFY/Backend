<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name' => 'sometimes|required|string|max:255',
            'service_description' => 'nullable|string',
            'services_category' => 'sometimes|required|string|max:255',
        ];
    }
}
