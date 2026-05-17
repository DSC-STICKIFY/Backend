<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Order fields ──────────────────────────────────────────────
            'user_id'        => 'required|exists:users_table,user_id',
            'courier'        => 'required|string|max:255',
            'total_price'    => 'required|numeric|min:0',
            'status'         => 'sometimes|nullable|string|max:50',
            'order_date'     => 'nullable|date',
            'contact_number' => 'nullable|string|max:50',
            'payment_method' => 'required|string|in:COD,GCASH,PICKUP',

            // ── FIX: address was missing — it was being stripped by validated() ──
            'address'        => 'nullable|string|max:500',

            // ── Promotions / Discounts ────────────────────────────────────
            'promotion_id'   => 'nullable|integer|exists:promotions,promotion_id',
            'discount_amount'=> 'nullable|numeric|min:0',

            // ── Items ─────────────────────────────────────────────────────
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|integer',
            'items.*.product_name'    => 'nullable|string|max:255',
            'items.*.quantity'        => 'required|integer|min:1',
            'items.*.item_price'      => 'required|numeric|min:0',
            'items.*.subtotal'        => 'nullable|numeric|min:0',
            'items.*.size'            => 'nullable|string|max:100',
            'items.*.comments'        => 'nullable|string|max:500',
            'items.*.category'        => 'nullable|string|max:255',
            'items.*.type'            => 'nullable|string|max:255',
            // 'items.*.pieces'       => 'nullable|integer',
            'items.*.order_image'     => 'nullable|file|image|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'              => 'User ID is required.',
            'user_id.exists'                => 'Invalid user.',
            'promotion_id.exists'           => 'The selected promotion/discount is invalid or no longer exists.',
            'courier.required'              => 'Please select a courier.',
            'total_price.required'          => 'Total price is required.',
            'total_price.min'               => 'Total price must be at least 0.',
            'payment_method.required'       => 'Please select a payment method.',
            'payment_method.in'             => 'Invalid payment method. Accepted: COD, GCash, Pickup.',
            'items.required'                => 'At least one item is required.',
            'items.array'                   => 'Items must be an array.',
            'items.min'                     => 'At least one item is required.',
            'items.*.product_id.required'   => 'Product ID is required for each item.',
            'items.*.quantity.required'     => 'Quantity is required for each item.',
            'items.*.quantity.min'          => 'Quantity must be at least 1.',
            'items.*.item_price.required'   => 'Item price is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        // ── Normalize payment method to uppercase ─────────────────────────
        if ($this->has('payment_method')) {
            $merged['payment_method'] = strtoupper($this->payment_method);
        }

        // ── Default status ────────────────────────────────────────────────
        if (!$this->has('status')) {
            $merged['status'] = 'Pending';
        }

        if (!empty($merged)) {
            $this->merge($merged);
        }
    }
}