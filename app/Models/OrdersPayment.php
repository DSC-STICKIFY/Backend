<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdersPayment extends Model
{
    protected $table = 'orders_payments_table';

    protected $fillable = [
        'order_id',
        'payment_amount',
        'amount_paid',
        'payment_date',
        'reference_number',
    ];

    public function paymentOrder()
    {
        return $this->belongsTo(OrdersModel::class, 'order_id', 'order_id');
    }
}
