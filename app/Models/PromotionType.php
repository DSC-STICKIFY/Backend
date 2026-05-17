<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionType extends Model
{
    protected $table = 'promotion_types';
    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'type_name',
    ];
}