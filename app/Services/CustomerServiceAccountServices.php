<?php

namespace App\Services;

use App\Interfaces\AuthServices;
use App\Models\StaffModel;
use Illuminate\Support\Facades\Hash;

class CustomerServiceAccountServices implements AuthServices
{
    public function login(array $data): ?StaffModel
    {
        $staff = StaffModel::where('email', $data['email'])
            ->where('role', 'customer_service')
            ->first();

        if ($staff && Hash::check($data['password'], $staff->password)) {
            return $staff;
        }

        return null;
    }

    public function register(array $data): ?StaffModel
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return StaffModel::create($data);
    }

    public function logout(): bool
    {
        $user = auth('staff_api')->user() ?? auth('sanctum')->user();

        if (!$user) {
            return false;
        }

        $user->currentAccessToken()->delete();

        return true;
    }
}
