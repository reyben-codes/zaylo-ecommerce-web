<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token-authenticated counterpart of the web's active + verified + role:buyer
 * stack. It answers with JSON instead of redirects or session changes.
 */
class EnsureMobileBuyer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your account is pending approval or has been suspended.', 'code' => 'inactive'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Verify your email address to continue.', 'code' => 'unverified', 'email' => $user->email], 403);
        }

        if (! $user->hasRole('buyer')) {
            return response()->json(['message' => 'The ZAYLO app is for buyer accounts. Sellers and couriers can use the website.', 'code' => 'not_buyer'], 403);
        }

        return $next($request);
    }
}
