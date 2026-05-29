<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomizationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'product_id',
        'product_name',
        'quantity',
        'material_type',
        'size_requested',
        'instructions',
        'reference_image',
        'validation_status',
        'validation_notes',
        'approved_quantity',
        'artist_id',
        'mockup_image',
        'design_status',
        'needs_revision_period',
        'revision_deadline',
        'revision_count',
        'production_date',
        'admin_design_notes',
        'qc_status',
        'qc_notes',
        'cs_notes',
        'status',
        'quotation_total',
        'order_id',
        'in_progress_at',
        'expected_shipped_at',
        'expected_delivery_at',
    ];

    protected $casts = [
        'quotation_total' => 'decimal:2',
        'needs_revision_period' => 'boolean',
        'revision_deadline' => 'datetime',
        'production_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(UserModel::class, 'customer_id', 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'product_id');
    }

    public function artist()
    {
        return $this->belongsTo(EmployeeModel::class, 'artist_id', 'employee_id');
    }

    public function quotation()
    {
        return $this->hasOne(Quotation::class);
    }

    public function order()
    {
        return $this->belongsTo(OrdersModel::class, 'order_id', 'order_id');
    }
}
