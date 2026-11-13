<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeliveryRider
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isDeliveryRider() || ! $user->rider) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied: this area is for delivery riders only.',
                    'code' => 'delivery_rider_only',
                ], 403);
            }
            abort(403, 'Access denied: this area is for delivery riders only.');
        }

        if (! $user->isActive() || $user->rider->status !== 'approved') {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Your rider account is not active. Contact your logistics partner.',
                    'code' => 'inactive',
                ], 403);
            }
            abort(403, 'Your rider account is not active. Contact your logistics partner.');
        }

        return $next($request);
    }
}

