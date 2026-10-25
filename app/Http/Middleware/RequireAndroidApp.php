<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends shoppers on Android devices to the "get the app" page instead of the web store (config
 * marketplace.android_app). Only page visits are redirected; the seller, logistics and admin portals, policies,
 * password resets and email verification links stay open, as do signed-in sellers, logistics partners and admins.
 */
class RequireAndroidApp
{
    /** Pages that stay open on Android: portals without an app, legal pages, and links opened from emails. */
    private const OPEN_PATHS = [
        'get-the-app', 'up',
        'seller', 'seller/*', 'logistics', 'logistics/*', 'admin', 'admin/*', 'sorting-center', 'sorting-center/*',
        'policies/*', 'terms', 'forgot-password', 'reset-password/*', 'email/verify', 'email/verify/*',
    ];

    /** Accounts that work on the website only. */
    private const WEB_ROLES = ['seller', 'logistics', 'admin', 'sorting_center'];

    /** Sign-up forms for accounts that have no app. */
    private const WEB_SIGN_UPS = ['seller', 'logistics'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->sendsToApp($request)) {
            return redirect()->route('app.download');
        }

        return $next($request);
    }

    public static function isAndroid(Request $request): bool
    {
        return str_contains((string) $request->userAgent(), 'Android');
    }

    private function sendsToApp(Request $request): bool
    {
        return config('marketplace.android_app.required')
            && $request->isMethod('GET')
            && ! $request->expectsJson()
            && self::isAndroid($request)
            && ! $request->is(...self::OPEN_PATHS)
            && ! in_array($request->user()?->role, self::WEB_ROLES, true)
            && ! ($request->is('register') && in_array($request->query('role'), self::WEB_SIGN_UPS, true));
    }
}
