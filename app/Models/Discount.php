<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $table = 'discounts_table';

    protected $fillable = [
        'discount_amount',
        'discount_name',
        'discount_code',
        'date_valid',
    ];
}
