<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The production site is served through Cloudflare. Trust the immediate
        // proxy so Laravel honors X-Forwarded-Proto and generates HTTPS URLs.
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => match (true) {
            $request->is('seller', 'seller/*') => route('seller.login'),
            $request->is('logistics', 'logistics/*') => route('logistics.login'),
            $request->is('admin', 'admin/*') => route('admin.login'),
            default => route('login'),
        });

        $middleware->alias([
            'role'     => \App\Http\Middleware\RoleMiddleware::class,
            'nocache'  => \App\Http\Middleware\NoCacheHeaders::class,
            'active'   => \App\Http\Middleware\EnsureAccountIsActive::class,
            'throttle' => \App\Http\Middleware\ThrottleRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['otp']);
    })->create();
