<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrdersModel extends Model
{
    protected $table = 'orders_table';
    protected $primaryKey = 'order_id';

    protected $fillable = [
        'order_number',
        'user_id',
        'artist_id',
        'promotion_id',
        'discount_amount',
        'courier',
        'order_date',
        'total_price',
        'status',
        'has_review',
        'payment_method',
        'paymongo_source_id',
        'contact_number',
        'address',
        'return_reason',
        'return_details',
        'tracking_number',
        'shipped_at',
        'dispatched_at',
        'delivery_deadline',
        'auto_completed_at',
        'in_progress_at',
        'expected_shipped_at',
        'expected_delivery_at',
        'final_design_url',
        'customer_approved_at',
        'shipment_requested_at',
        'shipment_note',
        'cancel_reason',
        'refund_status',
        // Manual validation workflow fields
        'cs_review_status',
        'staff_validation_status',
        'manual_approved_quantity',
        'staff_validation_note',
        'rejection_reason',
    ];

    protected $casts = [
        'total_price'          => 'decimal:2',
        'discount_amount'      => 'decimal:2',
        'order_date'           => 'datetime',
        'has_review'           => 'boolean',
        'shipped_at'           => 'datetime',
        'dispatched_at'        => 'datetime',
        'delivery_deadline'    => 'datetime',
        'auto_completed_at'    => 'datetime',
        'in_progress_at'       => 'datetime',
        'expected_shipped_at'  => 'datetime',
        'expected_delivery_at' => 'datetime',
        'customer_approved_at' => 'datetime',
        'shipment_requested_at'=> 'datetime',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    protected $attributes = [
        'status'         => 'Pending',
        'payment_method' => 'COD',
        'total_price'    => 0.00,
        'has_review'     => false,
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'user_id')
            ->withDefault(['first_name' => 'Guest', 'last_name' => '']);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(ArtistModel::class, 'artist_id', 'employee_id');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetails::class, 'order_id', 'order_id');
    }

    public function orderPayment(): HasOne
    {
        return $this->hasOne(OrdersPayment::class, 'order_id', 'order_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(PromotionModel::class, 'promotion_id', 'promotion_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'order_id', 'order_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'order_id', 'order_id');
    }

    public function returnRefund()
    {
        return $this->hasMany(ReturnRefundModel::class, 'order_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    protected function formattedTotalPrice(): Attribute
    {
        return Attribute::make(
            get: fn() => '₱' . number_format((float) $this->total_price, 2)
        );
    }

    protected function resolvedAddress(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->address
                ?? $this->user?->address
                ?? 'N/A'
        );
    }

    /**
     * Returns a human-readable countdown string for auto-completion.
     * e.g. "2 days, 3 hours", "45 minutes", or null if not applicable.
     */
    protected function autoCompleteCountdown(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->auto_completed_at || $this->status !== 'To Receive') {
                    return null;
                }

                $diff = now()->diff($this->auto_completed_at);

                // Already past deadline — scheduler will catch it on next run
                if (now()->greaterThanOrEqualTo($this->auto_completed_at)) {
                    return 'Completing soon...';
                }

                if ($diff->days > 0) {
                    $hours = $diff->h;
                    return $diff->days . 'd ' . ($hours > 0 ? $hours . 'h' : '') . ' remaining';
                }

                if ($diff->h > 0) {
                    return $diff->h . 'h ' . $diff->i . 'm remaining';
                }

                return $diff->i . ' minute(s) remaining';
            }
        );
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    /**
     * Scope: orders eligible for auto-completion right now.
     * Conditions:
     *  - status is exactly 'To Receive'
     *  - auto_completed_at is set
     *  - current time has reached or passed auto_completed_at
     */
        public function scopePendingAutoComplete($query)
    {
        return $query
            ->whereIn('status', ['To Receive', 'Shipped', 'Item Ready'])
            ->whereNotNull('delivery_deadline')
            ->whereNull('auto_completed_at')
            ->whereRaw('delivery_deadline + INTERVAL 24 HOUR < NOW()');
    }

    // ── Helper Methods ────────────────────────────────────────────────────────

    /**
     * Check whether this order is eligible for auto-completion.
     */
    public function isEligibleForAutoComplete(): bool
    {
        return $this->status === 'To Receive'
            && $this->auto_completed_at !== null
            && now()->greaterThanOrEqualTo($this->auto_completed_at);
    }

    /**
     * Mark the order as auto-completed.
     * Safe: does nothing if already Completed.
     */
    public function markAutoCompleted(): bool
    {
        if ($this->status === 'Completed') {
            return false; // already done manually
        }

        $this->update(['status' => 'Completed']);

        // Also sync all order detail items
        $this->orderDetails()->update(['status' => 'Completed']);

        return true;
    }

    // ── Model Events ──────────────────────────────────────────────────────────

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->contact_number) && $order->user) {
                $order->contact_number = $order->user->contact_number ?? null;
            }
            $order->order_number = 'TEMP-' . time();
        });

        static::created(function ($order) {
            $number = str_pad($order->order_id, 6, '0', STR_PAD_LEFT);
            $order->updateQuietly([
                'order_number' => 'ORD-' . now()->format('Ymd') . '-' . $number,
            ]);
        });
    }
}