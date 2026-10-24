<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mobile API's version of the web's active + verified + role:buyer checks, answering with JSON
 * instead of redirects. A suspended account also loses the token it used.
 */
class EnsureMobileBuyer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasRole('buyer')) {
            return response()->json(['message' => 'This part of the app is for buyer accounts.', 'code' => 'not_buyer'], 403);
        }

        if (! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your account is suspended. Contact ZAYLO support for help.', 'code' => 'inactive'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Verify your email address to continue.', 'code' => 'unverified', 'email' => $user->email], 403);
        }

        return $next($request);
    }
}
