<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function send(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Lock the user as well as the challenge, including the first send.
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($account->hasVerifiedEmail()) {
                return;
            }

            $challenge = DB::table('email_verification_codes')->where('user_id', $account->id)->first();
            if ($challenge && Carbon::parse($challenge->sent_at)->addMinute()->isFuture()) {
                throw ValidationException::withMessages(['otp' => 'Please wait one minute before requesting another code.']);
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            DB::table('email_verification_codes')->updateOrInsert(['user_id' => $account->id], [
                'email' => $account->email,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'sent_at' => now(),
            ]);

            // A transport failure rolls back the new challenge, allowing another send.
            $account->notify(new VerifyEmailCode($code));
        });
    }

    public function verify(User $user, string $code): void
    {
        $verified = false;
        $error = DB::transaction(function () use ($user, $code, &$verified) {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($account->hasVerifiedEmail()) {
                return null;
            }

            $query = DB::table('email_verification_codes')->where('user_id', $account->id);
            $challenge = $query->first();
            if (! $challenge || $challenge->email !== $account->email || Carbon::parse($challenge->expires_at)->lessThanOrEqualTo(now())) {
                return 'Your code has expired or is unavailable. Please request a new code.';
            }
            if ($challenge->attempts >= 5) {
                return 'Too many incorrect attempts. Please request a new code.';
            }
            if (! Hash::check($code, $challenge->code_hash)) {
                $query->increment('attempts');

                return $challenge->attempts >= 4
                    ? 'Too many incorrect attempts. Please request a new code.'
                    : 'That code is incorrect. Please check your email and try again.';
            }

            $account->markEmailAsVerified();
            $query->delete();
            $verified = true;

            return null;
        });

        // Throw outside the transaction so incorrect-attempt counts are retained.
        if ($error !== null) {
            throw ValidationException::withMessages(['otp' => $error]);
        }
        $user->refresh();
        if ($verified) {
            event(new Verified($user));
        }
    }
}
