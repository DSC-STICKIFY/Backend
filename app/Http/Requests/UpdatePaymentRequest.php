<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'service_id' => 'sometimes|required|exists:services,services_id',
            'employee_id' => 'sometimes|required|exists:employees,employee_id',
            'product_id' => 'sometimes|required|exists:products_table,product_id',
            'payment_amount' => 'sometimes|required|numeric|min:1',
            'customer' => 'sometimes|required|string|max:255',
            'payment_date' => 'sometimes|required|date',
            'quantity' => 'sometimes|integer|min:1',
        ];
    }
}
