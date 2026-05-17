<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeModel extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $primaryKey = 'employee_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'password',
        'role',
        'address',
        'contact_number',
    ];

    public function payments()
    {
        return $this->hasMany(ServicePaymentModel::class, 'employee_id');
    }
}
