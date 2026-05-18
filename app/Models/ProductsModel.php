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
    ];

        public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
