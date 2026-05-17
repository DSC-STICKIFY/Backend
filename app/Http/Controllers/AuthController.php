<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminRegistrationForm;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistrationRequestForm;
use App\Http\Requests\SubAdminRegistrationRequest;
use App\Services\AuthenticationServices;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthenticationServices $services;

    public function __construct(AuthenticationServices $services)
    {
        $this->services = $services;
    }

    public function authLogin(LoginRequest $request)
    {
        $result = $this->services->login($request->validated());

        return response()->json($result, $result['status'] ?? 200);
    }

    public function authRegister(RegistrationRequestForm $request)
    {
        $result = $this->services->register($request->validated(), 'user');

        return response()->json($result, $result['status'] ?? 201);
    }

    public function adminAuthRegister(AdminRegistrationForm $request)
    {
        $result = $this->services->register($request->validated(), 'admin');

        return response()->json($result, $result['status'] ?? 201);
    }

    public function subAdminAuthRegister(SubAdminRegistrationRequest $request)
    {
        $result = $this->services->register($request->validated(), 'subadmin');

        return response()->json($result, $result['status'] ?? 201);
    }

    public function artistAuthRegister(SubAdminRegistrationRequest $request) 
    {
        // Reusing SubAdminRegistrationRequest since fields are likely identical
        $result = $this->services->register($request->validated(), 'artist');

        return response()->json($result, $result['status'] ?? 201);
    }

    public function authLogout()
    {
        return response()->json($this->services->logoutAccount(), 200);
    }

    public function getUser()
    {
        return response()->json([
            'user' => auth()->user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users_table,email,' . auth()->id() . ',user_id',
            'contact_number' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'receive_promotional_emails' => 'sometimes|boolean',
        ]);

        $result = $this->services->updateProfile(auth()->user(), $request->all());

        return response()->json($result, $result['status'] ?? 200);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->services->updatePassword(auth()->user(), $validated);

        return response()->json($result, $result['status'] ?? 200);
    }

    public function deleteAccount()
    {
        $result = $this->services->deleteAccount(auth()->user());

        return response()->json($result, $result['status'] ?? 200);
    }
}