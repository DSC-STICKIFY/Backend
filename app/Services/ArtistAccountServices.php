<?php

namespace App\Services;

use App\Interfaces\AuthServices;
use App\Models\ArtistModel;
use Illuminate\Support\Facades\Hash;

class ArtistAccountServices implements AuthServices
{
    public function login(array $data): ?ArtistModel
    {
        $artist = ArtistModel::where('email', $data['email'])->first();

        if (!$artist || !Hash::check($data['password'], $artist->password)) {
            return null;
        }

        return $artist;
    }

    public function register(array $data): ?ArtistModel
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return ArtistModel::create($data);
    }

    public function logout(): bool
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return false;
        }

        $user->currentAccessToken()->delete();

        return true;
    }
}
