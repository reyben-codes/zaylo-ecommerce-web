<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mobile API's version of the web's active + verified + role:courier checks for the rider app,
 * answering with JSON instead of redirects.
 */
class EnsureMobileRider
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasRole('rider', 'courier') || ! $user->rider) {
            return response()->json(['message' => 'This part of the app is for rider accounts.', 'code' => 'not_rider'], 403);
        }

        if (! $user->isActive() || $user->rider->status !== 'approved') {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your rider account is not active. Contact your logistics partner.', 'code' => 'inactive'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Verify your email address to continue.', 'code' => 'unverified', 'email' => $user->email], 403);
        }

        return $next($request);
    }
}
