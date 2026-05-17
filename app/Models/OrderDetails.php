<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetails extends Model
{
    protected $table = 'orders_details_table';
    protected $primaryKey = 'order_details_id';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'size',
        'comments',
        'item_price',
        'subtotal',
        'status',
        'has_review',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'item_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'has_review' => 'boolean',
    ];

    protected $attributes = [
        'has_review' => false,
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrdersModel::class, 'order_id', 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'product_id');
    }

    /**
     * Optional: Link to a review if reviews are per order detail item
     */
    public function review()
    {
        return $this->hasOne(Review::class, 'order_detail_id', 'order_details_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Get formatted subtotal with currency symbol
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return '₱' . number_format($this->subtotal, 2);
    }

    /**
     * Get formatted item price with currency symbol
     */
    public function getFormattedItemPriceAttribute(): string
    {
        return '₱' . number_format($this->item_price, 2);
    }
}