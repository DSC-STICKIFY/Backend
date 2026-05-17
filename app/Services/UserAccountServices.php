<?php

namespace App\Services;

use App\Interfaces\AuthServices;
use App\Models\UserModel;
use Illuminate\Support\Facades\Hash;

class UserAccountServices implements AuthServices
{
    /**
     * LOGIN LOGIC
     */
    public function login(array $data): ?UserModel
    {
        $user = UserModel::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return null;
        }

        // ✅ CRUCIAL — dili mo-match kung admin ang user
        if ($user->is_admin) {
            return null;
        }

        return $user;
    }

    /**
     * REGISTER LOGIC
     */
    public function register(array $data): ?UserModel
    {
        // 1. Hash the password here (Only once!)
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // 2. Auto-generate username if not provided
        if (empty($data['username'])) {
            $base = strtolower(preg_replace('/\s+/', '', $data['first_name'] ?? 'user'));
            $data['username'] = $base . rand(1000, 9999);
            // Ensure uniqueness
            while (UserModel::where('username', $data['username'])->exists()) {
                $data['username'] = $base . rand(1000, 9999);
            }
        }

        // 3. Save to database
        $user = UserModel::create($data);

        // 4. Send email verification notification
        if ($user) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    /**
     * LOGOUT LOGIC
     */
    public function logout(): bool
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            return false;
        }

        $user->currentAccessToken()->delete();

        return true;
    }
}
