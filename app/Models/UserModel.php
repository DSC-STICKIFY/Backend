<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class UserModel extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    // Append the full URL for the profile image (used by the frontend)
    protected $appends = ['profile_image_url'];

    protected $table = 'users_table';
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'username',
        'date_of_birth',
        'address',
        'contact_number',
        'email',
        'password',
        'role',
        'is_admin',
        'email_verified_at', 
        'last_verification_sent_at',
        'receive_promotional_emails',
        'profile_image',
        'is_bot_active',
    ];

    protected $hidden = ['password'];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_verification_sent_at' => 'datetime',
        'receive_promotional_emails' => 'boolean',
        'is_bot_active' => 'boolean',
    ];

    // ✅ MustVerifyEmail contract methods
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\CustomVerifyEmail);
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    // ✅ Keep existing methods as-is
    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = ucfirst($value);
    }

    public function setMiddleNameAttribute($value)
    {
        $this->attributes['middle_name'] = ucfirst($value);
    }

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = ucfirst($value);
    }

    public function getProfileImageUrlAttribute()
    {
        return $this->profile_image
            ? url('storage/' . $this->profile_image)
            : null;
    }


    public function orders()
    {
        return $this->hasMany(OrdersModel::class, 'user_id', 'user_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}