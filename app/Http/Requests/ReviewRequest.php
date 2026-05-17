<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'order_id'          => 'required|exists:orders_table,order_id',
            'product_id'        => 'required|exists:products_table,product_id',
            'order_details_id'  => 'required|exists:orders_details_table,order_details_id', // ← important
            'rating'            => 'required|integer|min:1|max:5',
            'comment'           => 'nullable|string|max:1000',
            'artist_rating'     => 'nullable|integer|min:1|max:5',
            'artist_comment'    => 'nullable|string|max:1000',
            'rider_rating'      => 'nullable|integer|min:1|max:5',
            'rider_comment'     => 'nullable|string|max:1000',
        ];
    }
}