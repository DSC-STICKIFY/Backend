<?php

namespace App\Services;

use App\Models\AdminModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationServices
{
    protected array $services;

    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function login(array $data): array
    {
        if (empty($data['email']) || empty($data['password'])) {
            return [
                'success' => false,
                'message' => 'Email and password are required',
                'status'  => 400,
            ];
        }

        foreach ($this->services as $service) {
            $account = $service->login($data);

            if ($account) {
                $role  = strtolower($account->role ?? $this->getRoleFromService($service));
                $token = $account->createToken("{$role}_login_token")->plainTextToken;

                return [
                    'user_id' => $account->user_id ?? $account->admin_id ?? $account->sub_admin_id ?? $account->artist_id ?? $account->employee_id,
                    'success' => true,
                    'message' => 'Welcome, you are now logged in!',
                    'token'   => $token,
                    'user'    => [
                        'user_id'        => $account->user_id ?? $account->admin_id ?? $account->sub_admin_id ?? $account->artist_id ?? $account->employee_id,
                        'first_name'     => $account->first_name,
                        'last_name'      => $account->last_name,
                        'role'           => $role,
                        'email'          => $account->email    ?? null,
                        'address'        => $account->address  ?? '',
                        'contact_number' => $account->contact_number ?? '',
                    ],
                    'status' => 200,
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Invalid credentials',
            'status'  => 401,
        ];
    }

    public function register(array $data, string $role): array
    {
        $role = strtolower($role);

        if ($role === 'admin' && AdminModel::exists()) {
            return [
                'success' => false,
                'message' => 'An admin already exists',
                'status'  => 400,
            ];
        }

        if ($role === 'subadmin') {
            $currentUser = auth('sanctum')->user();

            if (!$currentUser || ($currentUser->role ?? '') !== 'admin') {
                return [
                    'success' => false,
                    'message' => 'Only admin can register a subadmin',
                    'status'  => 403,
                ];
            }
        }

        foreach ($this->services as $service) {
            if ($this->handlesRole($service, $role)) {
                $account = $service->register($data);

                if ($account) {
                    $token = $account->createToken("{$role}_registration_token")->plainTextToken;

                    return [
                        'user_id' => $account->user_id ?? $account->admin_id ?? $account->sub_admin_id ?? $account->artist_id ?? $account->staff_id ?? $account->employee_id,
                        'success' => true,
                        'message' => ucfirst($role) . ' account successfully registered!',
                        'token' => $token,
                        'user' => [
                            'user_id' => $account->user_id ?? $account->admin_id ?? $account->sub_admin_id ?? $account->artist_id ?? $account->staff_id ?? $account->employee_id,
                            'first_name' => $account->first_name,
                            'last_name' => $account->last_name,
                            'role'           => $role,
                            'email'          => $account->email    ?? null,
                            'address'        => $account->address  ?? '',
                            'contact_number' => $account->contact_number ?? '',
                        ],
                        'status' => 201,
                    ];
                }
            }
        }

        return [
            'success' => false,
            'message' => 'An error occurred during registration',
            'status'  => 400,
        ];
    }

    public function logoutAccount(): array
    {
        $user = auth('sanctum')->user();

        if ($user) {
            $token = $user->currentAccessToken();
            if ($token) {
                $token->delete();
            } else {
                $bearerToken = request()->bearerToken();
                if ($bearerToken) {
                    $accessToken = PersonalAccessToken::findToken($bearerToken);
                    if ($accessToken && $accessToken->tokenable_id === $user->getKey()) {
                        $accessToken->delete();
                    }
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Successfully logged out',
        ];
    }

    public function updateProfile($user, array $data): array
    {
        try {
            if (isset($data['profile_image']) && $data['profile_image'] instanceof \Illuminate\Http\UploadedFile) {
                if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                    Storage::disk('public')->delete($user->profile_image);
                }
                $user->profile_image = $data['profile_image']->store('profile_images', 'public');
            }

            $user->fill(collect($data)->except(['profile_image', '_method'])->toArray());
            $user->save();

            return [
                // ✅ $user, not $account
                'user_id' => $user->user_id ?? $user->admin_id ?? $user->sub_admin_id ?? $user->artist_id ?? $user->employee_id,
                'success' => true,
                'message' => 'Profile updated successfully.',
                'user' => [
                    'user_id'        => $user->user_id ?? $user->admin_id ?? $user->sub_admin_id ?? $user->artist_id ?? $user->employee_id,
                    'first_name'     => $user->first_name,
                    'last_name'      => $user->last_name,
                    'email'          => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'role'           => $user->role ?? 'user',
                    'address'        => $user->address,
                    'contact_number' => $user->contact_number ?? '',
                    'profile_image'  => $user->profile_image,
                    'receive_promotional_emails' => $user->receive_promotional_emails,
                ],
                'status' => 200,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update profile.',
                'status'  => 500,
            ];
        }
    }
    public function updatePassword($user, array $data): array
    {
        if (!Hash::check($data['current_password'], $user->password)) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect.',
                'status' => 422,
            ];
        }

        try {
            $user->password = Hash::make($data['new_password']);
            $user->save();

            return [
                'success' => true,
                'message' => 'Password updated successfully.',
                'status' => 200,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update password.',
                'status' => 500,
            ];
        }
    }

    public function deleteAccount($user): array
    {
        try {
            // Revoke all tokens first
            $user->tokens()->delete();
            $user->delete();

            return [
                'success' => true,
                'message' => 'Account deleted successfully.',
                'status' => 200,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete account.',
                'status' => 500,
            ];
        }
    }

        private function getRoleFromService($service): string
    {
        return match (true) {
            $service instanceof \App\Services\ArtistAccountServices  => 'artist',
            $service instanceof \App\Services\SubAdminAccountServices => 'subadmin',
            $service instanceof \App\Services\AdminAccountServices    => 'admin',
            $service instanceof \App\Services\StaffAccountServices    => 'staff',
            $service instanceof \App\Services\CustomerServiceAccountServices => 'customer_service',
            $service instanceof \App\Services\UserAccountServices     => 'user',
            default                                                    => 'user',
        };
    }

    private function handlesRole($service, string $role): bool
    {
        return match ($role) {
            'user'     => $service instanceof \App\Services\UserAccountServices,
            'admin'    => $service instanceof \App\Services\AdminAccountServices,
            'subadmin' => $service instanceof \App\Services\SubAdminAccountServices,
            'artist'   => $service instanceof \App\Services\ArtistAccountServices,
            'staff'    => $service instanceof \App\Services\StaffAccountServices,
            'customer_service' => $service instanceof \App\Services\CustomerServiceAccountServices,
            default    => false,
        };
    }
}