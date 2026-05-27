<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryMessage extends Model
{
    use HasFactory;

    protected $table = 'inquiry_messages';

    protected $fillable = [
        'inquiry_id',
        'sender_type', // 'user', 'admin', 'sub_admin', 'staff', 'customer_service'
        'sender_id',
        'message',
    ];

    // Relationships
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class, 'inquiry_id', 'id');
    }

    public function userSender()
    {
        return $this->belongsTo(UserModel::class, 'sender_id', 'user_id');
    }

    public function adminSender()
    {
        return $this->belongsTo(AdminModel::class, 'sender_id', 'admin_id');
    }

    public function subAdminSender()
    {
        return $this->belongsTo(SubAdminModel::class, 'sender_id', 'sub_admin_id');
    }

    public function employeeSender()
    {
        return $this->belongsTo(EmployeeModel::class, 'sender_id', 'employee_id');
    }

    // Dynamic attribute to resolve the sender instance easily
    public function getSenderNameAttribute(): string
    {
        if ($this->sender_type === 'user') {
            $user = $this->userSender;
            return $user && trim($user->first_name . ' ' . $user->last_name) ? trim($user->first_name . ' ' . $user->last_name) : 'Customer';
        }

        if ($this->sender_type === 'admin') {
            $admin = $this->adminSender;
            return $admin && trim($admin->first_name . ' ' . $admin->last_name) ? trim($admin->first_name . ' ' . $admin->last_name) : 'Admin';
        }

        if ($this->sender_type === 'subadmin' || $this->sender_type === 'sub_admin') {
            $sub = $this->subAdminSender;
            return $sub && trim($sub->first_name . ' ' . $sub->last_name) ? trim($sub->first_name . ' ' . $sub->last_name) : 'Sub Admin';
        }

        if ($this->sender_type === 'customer_service') {
            $emp = $this->employeeSender;
            return $emp && trim($emp->first_name . ' ' . $emp->last_name) ? trim($emp->first_name . ' ' . $emp->last_name) : 'Customer Service';
        }

        if ($this->sender_type === 'staff') {
            $emp = $this->employeeSender;
            return $emp && trim($emp->first_name . ' ' . $emp->last_name) ? trim($emp->first_name . ' ' . $emp->last_name) : 'Staff';
        }

        return 'System';
    }
}
