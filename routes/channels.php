<?php

use App\Models\AdminModel;
use App\Models\SubAdminModel;
use App\Models\UserModel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{userId}', function ($user, $userId) {

    // === ADMIN, SUBADMIN & ARTIST - Pwede mo-join sa TANAN nga chat ===
    if ($user instanceof AdminModel || 
        $user instanceof SubAdminModel || 
        in_array($user->role ?? null, ['admin', 'subadmin', 'artist'])) {
        return true;
    }

    // === CUSTOMER - Pwede lang sa iya kaugalingon nga chat ===
    if ($user instanceof UserModel) {
        return (int)($user->user_id ?? $user->id) === (int)$userId;
    }

    return false; // Default deny (security)
});