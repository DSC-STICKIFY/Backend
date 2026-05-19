<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductQuality extends Model
{
    use HasFactory;

    protected $table = 'product_qualities';

    protected $fillable = [
        'product_id',
        'quality_name',
        'description',
        'additional_price',
    ];

    protected $casts = [
        'additional_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'product_id');
    }
}
