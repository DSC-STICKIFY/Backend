<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email first.',
                'email_verified' => false,
            ], 403);
        }

        return $next($request);
    }
}