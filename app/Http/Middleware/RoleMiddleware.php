<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: route()->middleware('role:buyer') etc.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (in_array('pickup_rider', $roles, true)) {
            if (! $user->isPickupRider()) {
                abort(403, 'Unauthorized. This area is for pickup riders only.');
            }
            return $next($request);
        }

        if (in_array('delivery_rider', $roles, true)) {
            if (! $user->isDeliveryRider()) {
                abort(403, 'Unauthorized. This area is for delivery riders only.');
            }
            return $next($request);
        }

        $expanded = [];
        foreach ($roles as $role) {
            $expanded[] = $role;
            if ($role === 'courier') {
                $expanded[] = 'rider';
            } elseif ($role === 'rider') {
                $expanded[] = 'courier';
            }
        }

        if (! Auth::user()->hasRole(...array_unique($expanded))) {
            abort(403, 'Unauthorized. You do not have access to this area.');
        }

        return $next($request);
    }
}
