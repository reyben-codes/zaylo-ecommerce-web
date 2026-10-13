<?php

namespace App\Http\Controllers\Concerns;

use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Profile and password settings shared by every signed-in marketplace role.
 */
trait UpdatesAccountSettings
{
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:11', new PhoneNumber],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
        ]);

        if ($user->google_id && $validated['email'] !== $user->email) {
            throw ValidationException::withMessages([
                'email' => 'The email address linked to Google cannot be changed here.',
            ]);
        }

        $emailChanged = $validated['email'] !== $user->email;
        $user->fill($validated);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        if (! $emailChanged) {
            return back()->with('status', 'Profile settings updated.');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('verification.notice')
                ->withErrors(['otp' => 'Your email was updated, but we could not send a verification code. Please use Resend code.']);
        }

        return redirect()->route('verification.notice')->with('status', 'Profile updated. Verify your new email address to continue.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $hasPassword = filled($user->getAuthPassword());
        $validated = $request->validate([
            'current_password' => $hasPassword ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('status', $hasPassword ? 'Password updated.' : 'Password created. You can now also sign in with email.');
    }
}
