<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnMessageModel extends Model
{
    protected $table = 'return_messages';

    protected $fillable = [
        'return_id',
        'sender_type',
        'sender_id',
        'message',
    ];

    public function userSender()
    {
        return $this->belongsTo(\App\Models\UserModel::class, 'sender_id', 'user_id');
    }

    public function adminSender()
    {
        return $this->belongsTo(\App\Models\AdminModel::class, 'sender_id', 'admin_id');
    }

    public function subAdminSender()
    {
        return $this->belongsTo(\App\Models\SubAdminModel::class, 'sender_id', 'sub_admin_id');
    }

    public function getSenderAttribute()
    {
        if ($this->sender_type === 'admin') { // ✅ consistent with enum
            return $this->adminSender ?? $this->subAdminSender;
        }
        return $this->userSender;
    }
}