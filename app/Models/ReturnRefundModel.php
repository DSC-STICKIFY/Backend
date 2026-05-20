<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRefundModel extends Model
{
    use HasFactory;

    protected $table = 'returns_refunds';

        protected $fillable = [
        'order_id',
        'order_details_id',
        'product_id',
        'product_name',
        'user_id',
        'reason',
        'description',
        'refund_amount',
        'status',
        'gcash_number',
        'refund_proof',
        'refund_completed_at',
        'paymongo_refund_id',
        'subadmin_authorized',
    ];

    protected $casts = [
        'status'              => 'string',
        'refund_amount'       => 'decimal:2',
        'subadmin_authorized' => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrdersModel::class, 'order_id', 'order_id');
    }

    /**
     * The specific order item being returned.
     * Null if the return is for a whole order (legacy).
     */
    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetails::class, 'order_details_id', 'order_details_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ReturnMessageModel::class, 'return_id')
                    ->orderBy('created_at', 'asc');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReturnMediaModel::class, 'return_id');
    }
}