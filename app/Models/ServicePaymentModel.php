<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePaymentModel extends Model
{
    use HasFactory;

    protected $table = 'service_payments';

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'service_id',
        'employee_id',
        'product_id',
        'payment_amount',
        'payment_date',
        'customer',
        'invoice',
        'quantity',
    ];

    // Relationship to Service
    public function service()
    {
        return $this->belongsTo(ServiceModel::class, 'service_id', 'services_id');
    }

    // Relationship to Employee
    public function employee()
    {
        return $this->belongsTo(EmployeeModel::class, 'employee_id', 'employee_id');
    }

    // Relationship to Product
    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'product_id'); // assuming ProductsModel PK is 'id'
    }
}
