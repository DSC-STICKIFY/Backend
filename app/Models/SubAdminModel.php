<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class SubAdminModel extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = 'sub_admin_table';

    protected $primaryKey = 'sub_admin_id';

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
