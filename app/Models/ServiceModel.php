<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceModel extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $primaryKey = 'services_id';

    protected $fillable = [
        'service_name',
        'service_description',
        'services_category',
    ];

    public function payments()
    {
        return $this->hasMany(ServicePaymentModel::class, 'service_id');
    }
}
