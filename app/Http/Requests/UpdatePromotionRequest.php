<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy will gate update
    }

    public function rules(): array
    {
        return [
            'title'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'discount_type'   => 'required|in:percentage,fixed,free_shipping',
            'discount_value'  => 'required|numeric|min:0',
            'target_type'     => 'required|in:all_verified,recent_buyers,custom_order_customers,inactive_customers',
            'expiration_date'=> 'required|date|after:today',
            'banner_image'    => 'nullable|image|max:2048',
            'promo_code'      => 'nullable|string|max:50|unique:promotions,promo_code,' . $this->route('promotion')->id,
            'status'          => 'in:draft,pending_review,ready_to_send,cancelled,expired',
        ];
    }
}
