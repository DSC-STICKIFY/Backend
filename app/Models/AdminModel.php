<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AdminModel extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'admin_table';

    protected $primaryKey = 'admin_id';

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'address',
        'contact_number',
        'email',
        'password',
        'profile_image',
    ];

    protected $hidden = [
        'password',
    ];
}
