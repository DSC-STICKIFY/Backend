<?php

namespace App\Services;

use App\Interfaces\AuthServices;
use App\Models\SubAdminModel;
use Illuminate\Support\Facades\Hash;

class SubAdminAccountServices implements AuthServices
{
    public function login(array $data): ?SubAdminModel
    {
        $subadmin = SubAdminModel::where('email', $data['email'])->first();

        if (! $subadmin || ! Hash::check($data['password'], $subadmin->password)) {
            return null;
        }

        return $subadmin;
    }

    public function register(array $data): ?SubAdminModel
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']); // ✅ hash password
        }

        return SubAdminModel::create($data);
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
