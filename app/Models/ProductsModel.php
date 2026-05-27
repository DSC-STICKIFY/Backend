<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductsModel extends Model
{
    use HasFactory;

    protected $table = 'products_table';
    protected $primaryKey = 'product_id';

    protected $fillable = [
        'uuid',
        'product_name',
        'product_price',
        'product_category',
        'product_type',
        'product_image',
        'price_map_image',
        'wrap_price',
        'glossy_price',
        'hologram_price',
        'product_description',
        'is_car_service',
        'is_motor_service',
        'is_customizable',
        'product_quantity',
        'shelf_location',
    ];

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->uuid)) {
                $product->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function designs()
    {
        return $this->hasMany(ProductDesign::class, 'product_id', 'product_id');
    }

    public function qualities()
    {
        return $this->hasMany(ProductQuality::class, 'product_id', 'product_id');
    }

    public function sizes()
    {
        return $this->hasMany(ProductSize::class, 'product_id', 'product_id');
    }
}
