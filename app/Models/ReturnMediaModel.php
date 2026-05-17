<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnMediaModel extends Model
{
    use HasFactory;

    protected $table = 'return_media';

    protected $fillable = [
        'return_id',
        'file_path',
        'file_type',
    ];

    public function returnRefund(): BelongsTo
    {
        return $this->belongsTo(ReturnRefundModel::class, 'return_id');
    }
}