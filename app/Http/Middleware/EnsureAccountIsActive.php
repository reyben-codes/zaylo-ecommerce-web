<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->isActive()) {
            $redirectRoute = $request->user()->hasRole('seller') ? 'seller.login' : 'login';
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($redirectRoute)->with('error', 'Your account is pending approval or has been suspended.');
        }

        return $next($request);
    }
}
