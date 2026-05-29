<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

        protected $fillable = [
        'sender_id',
        'receiver_id',
        'product_id',
        'customization_request_id',
        'body',
        'image',
        'video',     
        'sender_type',
        'is_read',
        'is_bot',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_bot' => 'boolean',
    ];

    protected $attributes = [
        'is_read' => false,
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * Sender relationship — supports both admin and customer senders.
     */
    public function sender(): BelongsTo
    {
        if ($this->sender_type === 'admin') {
            return $this->belongsTo(AdminModel::class, 'sender_id', 'admin_id');
        }

        if ($this->sender_type === 'artist') {
            return $this->belongsTo(ArtistModel::class, 'sender_id', 'employee_id');
        }

        return $this->belongsTo(UserModel::class, 'sender_id', 'user_id');
    }


    public function receiver(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'receiver_id', 'user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeFromSender($query, int $senderId, string $senderType)
    {
        return $query->where('sender_id', $senderId)->where('sender_type', $senderType);
    }

    public function scopeToReceiver($query, int $receiverId)
    {
        return $query->where('receiver_id', $receiverId);
    }

    public function scopeBetween($query, int $userOneId, int $userTwoId)
    {
        return $query->where(function ($q) use ($userOneId, $userTwoId) {
            $q->where('sender_id', $userOneId)
              ->where('receiver_id', $userTwoId);
        })->orWhere(function ($q) use ($userOneId, $userTwoId) {
            $q->where('sender_id', $userTwoId)
              ->where('receiver_id', $userOneId);
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function markAsRead(): bool
    {
        if (! $this->is_read) {
            return $this->update(['is_read' => true]);
        }

        return true;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, 'http')
            ? $this->image
            : asset('storage/' . $this->image);
    }
}