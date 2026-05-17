<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_type',
        'customer_name',
        'contact_number',
        'email',
        'address',
        'car_type',
        'motor_model',
        'wrap_type',
        'finish_type',
        'decal_type',
        'placement',
        'size',
        'color_style',
        'image',
        'message',
        'admin_message',
        'quotation_amount',
        'schedule_date',
        'payment_status',
        'payment_method',
        'payment_intent_id',
        'payment_reference',
        'paid_at',
        'downpayment_amount',
        'amount_paid',
        'rejection_reason',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'user_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
