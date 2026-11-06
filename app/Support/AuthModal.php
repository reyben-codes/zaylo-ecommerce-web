<?php

namespace App\Support;

use App\Http\Controllers\HomeController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sign-in, sign-up, email-code and forgot-password forms open in one modal (resources/views/partials/auth-modal.blade.php,
 * public/js/auth-modal.js). The modal fetches a panel with the X-Auth-Modal header and gets just its HTML; any other
 * visit to the same URL (a typed or shared link, a redirect, or a browser without JavaScript) gets the home page with
 * that panel already open, so every form still works as an ordinary page.
 */
final class AuthModal
{
    public const HEADER = 'X-Auth-Modal';

    public static function respond(Request $request, string $panel, array $data = [], string $title = 'ZAYLO'): Response
    {
        $view = 'auth.panels.'.$panel;

        $response = $request->headers->has(self::HEADER)
            ? response()->view($view, $data)
            : response()->view('index', [
                'newArrivals' => HomeController::newArrivals(),
                'authModal' => ['view' => $view, 'data' => $data, 'title' => $title],
            ]);

        // The same URL answers with a fragment or a page, so caches must keep them apart.
        return $response->header('Vary', self::HEADER)->header('Cache-Control', 'no-store, private');
    }

    /** Paths whose links open in the modal instead of loading a page. */
    public static function paths(): array
    {
        return collect(['login', 'seller.login', 'logistics.login', 'admin.login', 'register', 'verification.notice', 'password.request'])
            ->map(fn (string $route) => parse_url(route($route), PHP_URL_PATH) ?: '/')
            ->values()
            ->all();
    }
}
