<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user && ($user instanceof \App\Models\AdminModel || $user instanceof \App\Models\SubAdminModel);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_amount' => 'required|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
        ];
    }
}
