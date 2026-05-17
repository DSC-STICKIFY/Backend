<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';

    protected $fillable = [
        'order_id',
        'order_details_id',
        'user_id',
        'product_id',
        'inquiry_id',
        'rating',
        'comment',
        'artist_rating',
        'artist_comment',
        'rider_rating',
        'rider_comment',
        'admin_reply',
        'status',
    ];

    protected $casts = [
        'rating' => 'integer',
        'artist_rating' => 'integer',
        'rider_rating' => 'integer',
        'status' => 'string',
    ];

    protected $attributes = [
        'status' => 'visible',
    ];

    public function order()
    {
        return $this->belongsTo(OrdersModel::class, 'order_id', 'order_id');
    }

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class, 'order_details_id', 'order_details_id');
    }

    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'product_id');
    }

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('status', 'visible');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}