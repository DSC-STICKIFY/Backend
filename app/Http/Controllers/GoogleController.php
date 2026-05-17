<?php

namespace App\Http\Controllers;

use App\Models\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    /**
     * Handle Google Login via ID Token (Credential) from Frontend.
     * Uses Google's tokeninfo endpoint to verify the JWT credential.
     */
    public function handleGoogleLogin(Request $request)
    {
        $idToken = $request->credential;

        if (!$idToken) {
            return response()->json([
                'success' => false,
                'message' => 'Credential (ID Token) is required.',
            ], 400);
        }

        try {
            // ✅ Verify ID Token with Google's tokeninfo endpoint
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            \Log::info('Google tokeninfo response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token. Please try again.',
                    'debug'   => $response->json(),
                ], 401);
            }

            $googlePayload = $response->json();

            // ✅ Validate audience — tokeninfo returns both 'aud' and 'azp'
            $validAudience = config('services.google.client_id');
            $tokenAud      = $googlePayload['aud'] ?? '';
            $tokenAzp      = $googlePayload['azp'] ?? '';

            if ($tokenAud !== $validAudience && $tokenAzp !== $validAudience) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token audience mismatch.',
                ], 401);
            }

            $email     = $googlePayload['email'] ?? null;
            $firstName = $googlePayload['given_name'] ?? $googlePayload['name'] ?? 'User';
            $lastName  = $googlePayload['family_name'] ?? '';

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not retrieve email from Google.',
                ], 400);
            }

            // ✅ Find or create the user
            $user = UserModel::where('email', $email)->first();
            $now = now();

            if (!$user) {
                // New user — register them automatically
                $base     = strtolower(preg_replace('/\s+/', '', $firstName));
                $username = $base . rand(100, 999);
                while (UserModel::where('username', $username)->exists()) {
                    $username = $base . rand(100, 999);
                }

                $user = UserModel::create([
                    'first_name'        => $firstName,
                    'last_name'         => $lastName,
                    'email'             => $email,
                    'password'          => Hash::make(Str::random(24)),
                    'email_verified_at' => $now,
                    'is_admin'          => false,
                    'role'              => 'user',
                    'username'          => $username,
                    'address'           => '',
                    'contact_number'    => '',
                    'date_of_birth'     => '2000-01-01', // placeholder — user can update in profile
                ]);
            } else {
                // ✅ If user exists but is not verified, mark them as verified 
                // since they just logged in with Google.
                if (is_null($user->email_verified_at)) {
                    $user->email_verified_at = $now;
                    $user->save();
                }
            }

            // ✅ Generate Sanctum token
            $token = $user->createToken('user_google_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Welcome to Stickify!',
                'token'   => $token,
                'user'    => [
                    'user_id'           => $user->user_id,
                    'first_name'        => $user->first_name,
                    'last_name'         => $user->last_name,
                    'role'              => 'user',
                    'email'             => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'address'           => $user->address ?? '',
                    'contact_number'    => $user->contact_number ?? '',
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Google Auth Failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Google Authentication Failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
