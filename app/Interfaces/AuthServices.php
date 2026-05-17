<?php

namespace App\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface AuthServices
{
    public function login(array $data): ?Model;

    public function logout();

    public function register(array $data);
    // Multiple user roles can benefit from this interface
}
