<?php

namespace Tests\Support;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Support\Facades\Notification;

/**
 * Sign-ups only become accounts once the emailed code is entered. Requires Notification::fake().
 */
trait CompletesSignUp
{
    /** Submit the sign-up form, enter the emailed code, and return the new account (signed out afterwards). */
    protected function signUp(array $data, bool $signOut = true): User
    {
        $this->post(route('register'), $data)->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));
        $this->post(route('verification.verify'), ['otp' => $this->signUpCode($data['email'])])->assertSessionHasNoErrors();
        $user = User::where('email', $data['email'])->firstOrFail();
        if ($signOut && auth()->check()) {
            $this->post(route('logout'));
        }

        return $user;
    }

    /** The newest code emailed to an address that has no account yet. */
    protected function signUpCode(string $email): string
    {
        $code = null;
        Notification::assertSentOnDemand(VerifyEmailCode::class, function (VerifyEmailCode $notification, array $channels, object $notifiable) use ($email, &$code) {
            if (($notifiable->routes['mail'] ?? null) === $email) {
                $code = $notification->code;
            }

            return true;
        });

        return $code ?? $this->fail("No sign-up code was emailed to {$email}.");
    }
}
