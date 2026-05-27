<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionLog extends Model
{
    protected $table = 'promotion_logs';

    protected $fillable = [
        'promotion_id',
        'sent_by',
        'sent_at',
        'total_recipients',
        'successful_sends',
        'failed_sends',
        'failed_emails',
    ];

    protected $casts = [
        'failed_emails' => 'array',
        'sent_at'       => 'datetime',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id', 'promotion_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'sent_by', 'user_id');
    }
}
