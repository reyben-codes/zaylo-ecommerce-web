<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            $request->is('courier', 'courier/*') => route('rider.login'),
            default => route('login'),
        });

        // Activity logs: records the actions listed in config/activity.php once they succeed.
        // Shoppers on Android are sent to download the app instead of using the web store.
        $middleware->web(append: [\App\Http\Middleware\RequireAndroidApp::class, \App\Http\Middleware\RecordActivity::class]);
        $middleware->api(append: [\App\Http\Middleware\RecordActivity::class]);

        $middleware->alias([
            'role'     => \App\Http\Middleware\RoleMiddleware::class,
            'nocache'  => \App\Http\Middleware\NoCacheHeaders::class,
            'active'   => \App\Http\Middleware\EnsureAccountIsActive::class,
            'throttle' => \App\Http\Middleware\ThrottleRequests::class,
            // Mobile app API (routes/api.php): JSON versions of the web's account checks.
            'mobile.buyer' => \App\Http\Middleware\EnsureMobileBuyer::class,
            'mobile.rider' => \App\Http\Middleware\EnsureMobileRider::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['otp']);
        // The mobile API always answers in JSON (401 for a missing or expired sign-in, 422 for form errors).
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
