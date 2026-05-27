<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $table = 'promotions';
    protected $primaryKey = 'promotion_id';

    protected $fillable = [
        'name',
        'title',
        'description',
        'type',
        'display_type',
        'discount_type',
        'discount_value',
        'min_quantity',
        'min_amount',
        'max_discount',
        'start_date',
        'end_date',
        'usage_limit',
        'target_type',
        'expiration_date',
        'banner_image',
        'promo_code',
        'status',
        'created_by',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'start_date'      => 'datetime',
        'end_date'        => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by', 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PromotionLog::class, 'promotion_id', 'promotion_id');
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            ProductsModel::class,
            'promotion_products',
            'promotion_id',
            'product_id'
        );
    }

    public function categories(): HasMany
    {
        return $this->hasMany(PromotionCategory::class, 'promotion_id', 'promotion_id');
    }

    public function types(): HasMany
    {
        return $this->hasMany(PromotionType::class, 'promotion_id', 'promotion_id');
    }

    /**
     * Helper to get a badge CSS class for status.
     */
    public function statusBadgeClass(): string
    {
        return match($this->status) {
            'draft' => 'bg-gray-200 text-gray-800',
            'pending_review' => 'bg-yellow-200 text-yellow-800',
            'ready_to_send' => 'bg-indigo-200 text-indigo-800',
            'sent' => 'bg-green-200 text-green-800',
            'expired' => 'bg-red-200 text-red-800',
            'cancelled' => 'bg-gray-300 text-gray-700',
            'active' => 'bg-green-200 text-green-800',
            'inactive' => 'bg-gray-200 text-gray-800',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'pending_review']);
    }
}
