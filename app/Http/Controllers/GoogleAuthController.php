<?php

namespace App\Http\Controllers;

use App\Services\GoogleAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $request->session()->forget(['google_oauth', 'state']);
        $role = $request->query('role', 'buyer');
        if (! is_string($role) || ! in_array($role, GoogleAccountService::PUBLIC_ROLES, true)) {
            return redirect()->route('login')->withErrors(['google' => 'Please choose a buyer, seller, or courier account.']);
        }
        if (! $this->configured()) {
            return $this->unconfigured();
        }

        try {
            $request->session()->put('google_oauth', ['role' => $role, 'started_at' => now()->timestamp]);

            // Stateful Socialite generates a cryptographic OAuth state in this session.
            return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
        } catch (\Throwable $exception) {
            $request->session()->forget(['google_oauth', 'state']);

            return $this->failed($exception);
        }
    }

    public function callback(Request $request, GoogleAccountService $accounts)
    {
        $context = $request->session()->pull('google_oauth');
        try {
            if (! $this->configured()) {
                return $this->unconfigured();
            }
            if ($request->has('error')) {
                return redirect()->route('login')->withErrors(['google' => 'Google sign-in was cancelled or denied. You can try again or use email and password.']);
            }
            if (! is_array($context) || ! in_array($context['role'] ?? null, GoogleAccountService::PUBLIC_ROLES, true)
                || ! is_int($context['started_at'] ?? null)
                || $context['started_at'] < now()->subMinutes(10)->timestamp
                || ! is_string($request->query('state')) || ! is_string($request->query('code'))
                || $request->query('code') === '') {
                throw new \DomainException('Missing or expired Google sign-in session.');
            }

            // Do not use stateless(): user() consumes and validates the session's state.
            $identity = Socialite::driver('google')->user();
            $user = $accounts->resolve($identity, $context['role']);
            if (! $user->isActive()) {
                $message = $user->status === 'pending'
                    ? 'Your email is verified. Your marketplace account is awaiting administrator approval.'
                    : 'This account is suspended. Please contact support.';

                return redirect()->route('login')->withErrors(['google' => $message]);
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route(match ($user->role) {
                'buyer' => 'buyer.dashboard',
                'seller' => 'seller.dashboard',
                'courier' => 'courier.dashboard',
                'admin' => 'admin.dashboard',
                default => 'home',
            });
        } catch (\Throwable $exception) {
            return $this->failed($exception);
        } finally {
            $request->session()->forget('state');
        }
    }

    private function configured(): bool
    {
        return (bool) (config('services.google.client_id') && config('services.google.client_secret') && config('services.google.redirect'));
    }

    private function unconfigured()
    {
        return redirect()->route('login')->withErrors(['google' => 'Google sign-in is not configured yet. Please use email and password for now.']);
    }

    private function failed(\Throwable $exception)
    {
        // OAuth exceptions can embed tokens, codes, and upstream response bodies.
        // Log only the exception type, never the exception object or identity payload.
        Log::warning('Google sign-in failed.', ['exception_type' => $exception::class]);

        return redirect()->route('login')->withErrors(['google' => 'We could not sign you in with Google. Please try again or use email and password.']);
    }
}
