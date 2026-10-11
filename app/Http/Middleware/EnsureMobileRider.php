<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token-authenticated counterpart of the web's active + verified + role:courier
 * stack, for the rider app. Answers with JSON instead of redirects.
 */
class EnsureMobileRider
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasRole('rider')) {
            return response()->json(['message' => 'This area is for rider accounts.', 'code' => 'not_rider'], 403);
        }

        if (! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your rider account is not active.', 'code' => 'inactive'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Verify your email address to continue.', 'code' => 'unverified', 'email' => $user->email], 403);
        }

        return $next($request);
    }
}
