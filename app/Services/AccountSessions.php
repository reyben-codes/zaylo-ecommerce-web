<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AccountSessions
{
    public function revokeMobileTokens(User $user, ?int $keepTokenId = null): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            $user->tokens()->when($keepTokenId !== null, fn ($tokens) => $tokens->whereKeyNot($keepTokenId))->delete();
        }
    }

    /** Snapshot at sign-in and refresh only the browser that authenticated the password change. */
    public function rememberWebPassword(User $user): void
    {
        $request = request();
        if ($request->hasSession()) {
            $request->session()->put('password_hash_web', Auth::guard('web')->hashPasswordForCookie((string) $user->getAuthPassword()));
        }
    }
}
