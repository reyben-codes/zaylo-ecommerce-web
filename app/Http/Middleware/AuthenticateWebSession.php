<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession;

class AuthenticateWebSession extends AuthenticateSession
{
    public function handle($request, Closure $next)
    {
        // Some media routes accept a mobile bearer guard within the web middleware stack.
        if ($this->auth->getDefaultDriver() !== 'web') {
            return $next($request);
        }
        // Sessions created before password snapshots were introduced need one fresh sign-in.
        // A remembered sign-in fires Login and receives its snapshot before this check.
        if ($request->hasSession() && $request->user()
            && $request->session()->has($this->guard()->getName())
            && ! $request->session()->has('password_hash_web')) {
            $this->logout($request);
        }

        return parent::handle($request, $next);
    }
}
