<?php

namespace App\Http\Requests;

use App;
use Illuminate\Foundation\Http\FormRequest;

class AddEmployeeRequest extends FormRequest
{
    /**
     * Determine if f is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        // Allow Admin or SubAdmin
        return $user instanceof App\Models\AdminModel || $user instanceof App\Models\SubAdminModel;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email',
            'password' => 'required|string|min:6',
            'address' => 'required|string|max:500',
            'contact_number' => 'required|string|max:20',
            'role' => 'required|string|in:Staff,Artist',
        ];
    }
}
