<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottleRequests;

/**
 * Laravel keys plain throttle:X,Y limits by user or IP only, so every throttled route shares one counter
 * (location lookups or chat polling would use up the sign-up or send allowance). Key each route separately.
 */
class ThrottleRequests extends BaseThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        return parent::resolveRequestSignature($request).($route ? '|'.sha1($request->method().'|'.$route->uri()) : '');
    }
}
