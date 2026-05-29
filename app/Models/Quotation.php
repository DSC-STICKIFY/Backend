<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customization_request_id',
        'material_cost',
        'printing_cost',
        'design_fee',
        'additional_charges',
        'additional_notes',
        'total',
    ];

    protected $casts = [
        'material_cost'      => 'decimal:2',
        'printing_cost'      => 'decimal:2',
        'design_fee'         => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'total'              => 'decimal:2',
    ];

    public function customizationRequest()
    {
        return $this->belongsTo(CustomizationRequest::class);
    }
}
