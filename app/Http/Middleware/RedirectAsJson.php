<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forms in the sign-in modal post in the background. Instead of a redirect (which fetch would follow, using up the
 * flashed message on a page nobody sees) they get {"redirect": url}; the modal then loads that panel or page itself.
 * Ordinary form posts are untouched.
 */
class RedirectAsJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->expectsJson() && $response instanceof RedirectResponse) {
            return response()->json(['redirect' => $response->getTargetUrl()]);
        }

        return $response;
    }
}
