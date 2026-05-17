<?php

namespace App\Services;

use App\Models\UserModel;
use Illuminate\Http\Request;

class EmailVerificationService
{
    public function verify(Request $request, $id, $hash): array
    {
        $user = UserModel::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return ['status' => 403, 'message' => 'Invalid verification link.'];
        }

        if (!$request->hasValidSignature()) {
            return ['status' => 403, 'message' => 'Verification link has expired.'];
        }

        if ($user->hasVerifiedEmail()) {
            return ['status' => 200, 'message' => 'Email already verified.'];
        }

        $user->markEmailAsVerified();

        return ['status' => 200, 'message' => 'Email verified successfully!'];
    }

    public function resend(Request $request): array
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ['status' => 200, 'message' => 'Email already verified.'];
        }

        // Rate limit resend (e.g., 1 minute)
        if ($user->last_verification_sent_at && $user->last_verification_sent_at->diffInMinutes(now()) < 1) {
            return [
                'status' => 429, 
                'message' => 'Please wait before requesting another verification email.'
            ];
        }

        $user->sendEmailVerificationNotification();
        
        $user->forceFill([
            'last_verification_sent_at' => now(),
        ])->save();

        return ['status' => 200, 'message' => 'Verification email sent!'];
    }

    public function status(Request $request): array
    {
        $user = $request->user();

        return [
            'status' => 200,
            'verified' => $user->hasVerifiedEmail(),
            'email' => $user->email,
            'last_verification_sent_at' => $user->last_verification_sent_at ? $user->last_verification_sent_at->toDateTimeString() : null,
            'receive_promotional_emails' => (bool) $user->receive_promotional_emails,
        ];
    }
}