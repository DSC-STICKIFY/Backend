<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PromotionModel extends Model
{
    use HasFactory;

    protected $table = 'promotions';
    protected $primaryKey = 'promotion_id';

    protected $fillable = [
    'name', 'description', 'type',
    'display_type',   
    'discount_type', 'discount_value',
    'min_quantity', 'min_amount', 'max_discount',
    'start_date', 'end_date',
    'usage_limit', 'status',
];

    protected $casts = [
        'start_date'     => 'datetime',
        'end_date'       => 'datetime',
        'min_quantity'   => 'integer',
        'usage_limit'    => 'integer',
        'discount_value' => 'decimal:2',
        'min_amount'     => 'decimal:2',
        'max_discount'   => 'decimal:2',
    ];

    // ✅ Read from promotion_products via direct query
    public function products()
    {
        return $this->belongsToMany(
            ProductsModel::class,
            'promotion_products',
            'promotion_id',
            'product_id'
        );
    }

    // ✅ Read from promotion_categories via hasMany
    public function categories()
    {
        return $this->hasMany(PromotionCategory::class, 'promotion_id', 'promotion_id');
    }

    public function types()
        {
            return $this->hasMany(PromotionType::class, 'promotion_id', 'promotion_id');
        }

        // Updated appliesToProduct()
        public function appliesToProduct($productId): bool
        {
            $hasProducts   = $this->products->isNotEmpty();
            $hasCategories = $this->categories->isNotEmpty();
            $hasTypes      = $this->types->isNotEmpty();

            // Global promo
            if (!$hasProducts && !$hasCategories && !$hasTypes) return true;

            // Direct product match
            if ($hasProducts && $this->products->contains('product_id', (int)$productId)) {
                return true;
            }

            // Category or type match — both need the product record
            if ($hasCategories || $hasTypes) {
                $product = ProductsModel::find($productId);
                if (!$product) return false;

                if ($hasCategories) {
                    $promoCategoryNames = $this->categories->pluck('category_name')->toArray();
                    if (in_array($product->product_category, $promoCategoryNames)) return true;
                }

                if ($hasTypes) {
                    $promoTypeNames = $this->types->pluck('type_name')->toArray();
                    if (in_array($product->product_type, $promoTypeNames)) return true;
                }
            }

            return false;
        }
}