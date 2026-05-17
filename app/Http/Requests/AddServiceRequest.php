<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name' => 'required|string|max:255',
            'service_description' => 'nullable|string',
            'services_category' => 'required|string|max:255',
        ];
    }
}
