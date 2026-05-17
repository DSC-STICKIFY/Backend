<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqModel extends Model
{
    protected $table = 'faqs';
    protected $fillable = ['question', 'answer', 'is_active'];
}
