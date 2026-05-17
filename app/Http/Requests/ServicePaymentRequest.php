<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServicePaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true; // allow all requests
    }

    public function rules()
    {
        return [
            'service_id' => 'required|exists:services,services_id',
            'employee_id' => 'required|exists:employees,employee_id',
            'product_id' => 'required|exists:products_table,product_id',
            'payment_amount' => 'required|numeric|min:1',
            'customer' => 'required|string|max:255',
            'payment_date' => 'required|date',
            'quantity' => 'sometimes|integer|min:1',
        ];
    }
}
