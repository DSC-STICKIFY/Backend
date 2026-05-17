<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionCategory extends Model
{
    protected $table = 'promotion_categories';
    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'category_name',
    ];
}