<?php

namespace App\Services;

use App\Interfaces\AuthServices;
use App\Models\AdminModel;
use Illuminate\Support\Facades\Hash;

class AdminAccountServices implements AuthServices
{
    public function login(array $data): ?AdminModel
    {
        $admin = AdminModel::where('email', $data['email'])->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            return null;
        }

        return $admin;
    }

    public function register(array $data): ?AdminModel
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']); // ✅ hash password
        }

        return AdminModel::create($data);
    }

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
