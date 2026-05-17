<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnPolicy extends Model
{
    protected $table = 'return_policies';

    protected $fillable = [
        'name',
        'allowed_value',
        'allowed_unit',
        'scope_type',
        'category_name',
        'type_name',
        'product_id',
        'is_returnable'
    ];

    protected $casts = [
        'allowed_value' => 'integer',
        'is_returnable' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'product_id');
    }
}
