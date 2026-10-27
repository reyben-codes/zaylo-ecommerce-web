<?php

namespace App\Http\Controllers;

use App\Exceptions\WrongAccountTypeException;
use App\Http\Controllers\Concerns\IssuesMobileTokens;
use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Services\ActivityLogger;
use App\Services\EmailVerificationService;
use App\Services\GoogleAccountService;
use App\Services\GoogleIdTokenVerifier;
use App\Services\PendingRegistrationService;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Buyer sign-in and sign-up for the mobile app, plus the endpoints buyers and riders share: who am I, sign out,
 * email codes and password reset. Like the website, a sign-up becomes an account only once its emailed code is entered.
 */
class MobileAuthenticationController extends Controller
{
    use IssuesMobileTokens;

    public function login(Request $request): JsonResponse
    {
        return $this->signInWithRole($request, 'buyer', 'This sign-in is for buyer accounts. Riders can use Login as a Rider; sellers and logistics partners use the website.');
    }

    /**
     * "Continue with Google" in the app. The phone sends the ID token Google gave it; the account is found, linked
     * or created by the same rules as the website's Google sign-in. Buyers only: riders apply to a partner by form.
     */
    public function google(Request $request, GoogleIdTokenVerifier $tokens, GoogleAccountService $accounts): JsonResponse
    {
        if (! $request->has('id_token')) {
            $token = $request->input('idToken') ?? $request->input('token') ?? $request->input('credential');
            if (is_string($token) && $token !== '') {
                $request->merge(['id_token' => $token]);
            }
        }

        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        if (! $tokens->configured()) {
            return response()->json(['message' => 'Google sign-in is not set up yet. Please use email and password for now.', 'code' => 'google_unconfigured'], 503);
        }

        try {
            $identity = $tokens->verify($validated['id_token']);
            if (! $identity) {
                throw ValidationException::withMessages(['google' => 'Google could not confirm this sign-in. Please try again.']);
            }
            $user = $accounts->resolve($identity, 'buyer');
        } catch (WrongAccountTypeException $exception) {
            $portal = User::portalOf($exception->accountRole);
            throw ValidationException::withMessages(['google' => $portal === 'rider'
                ? 'This Google email belongs to a rider account. Use Login as a Rider with your email and password.'
                : "This Google email belongs to a {$portal} account, which uses the ZAYLO website, not the app."]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            // Never log the token or Google's response; only what kind of failure it was.
            Log::warning('Mobile Google sign-in failed.', ['exception_type' => $exception::class]);
            throw ValidationException::withMessages(['google' => 'We could not sign you in with Google. Please try again or use email and password.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['google' => 'This account is suspended. Please contact ZAYLO support.']);
        }

        return $this->issueToken($user, $validated['device_name'] ?? null, 'Signed in with Google.');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => MobilePayload::user($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        app(ActivityLogger::class)->record('mobile.logout', 'auth', 'Signed out of the mobile app', $request->user());
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        // Same answer whether or not the account exists, so emails cannot be probed.
        return response()->json(['message' => 'If an account exists for that email, a password reset link is on its way.']);
    }

    public function register(Request $request, PendingRegistrationService $registrations): JsonResponse
    {
        // +63 9XX numbers are the same phone as 09XX; store one form so duplicates are caught either way.
        if (preg_match('/^\+639[0-9]{9}$/D', (string) $request->input('phone'))) {
            $request->merge(['phone' => '0'.substr($request->input('phone'), 3)]);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', new PersonName],
            'last_name' => ['required', 'string', 'min:2', 'max:50', new PersonName],
            'phone' => ['required', 'string', 'max:11', new PhoneNumber, 'unique:users,phone'],
            'date_of_birth' => ['required', 'bail', 'date', 'before_or_equal:today', function (string $attribute, mixed $value, \Closure $fail) {
                if (Carbon::parse($value)->startOfDay()->gt(today()->subYears(18))) {
                    $fail('You must be at least 18 years old to register.');
                }
            }],
            'email' => ['required', 'email:filter', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ], [
            'email.email' => 'Enter a valid email address, such as name@example.com.',
            'phone.unique' => 'This mobile number is already in use.',
            'date_of_birth.before_or_equal' => 'Enter a valid date of birth. It cannot be a future date.',
        ]) + ['role' => 'buyer'];

        return $this->startSignUp($request, $registrations, $validated);
    }

    public function verifyEmail(Request $request, EmailVerificationService $verification, PendingRegistrationService $registrations): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'otp.required' => 'Enter the verification code from your email.',
            'otp.regex' => 'Enter the six-digit code from your email.',
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])->first();
        if ($user) {
            if ($user->hasVerifiedEmail()) {
                return response()->json(['message' => 'This email address is already verified. You can sign in.']);
            }
            $verification->verify($user, $validated['otp']);
            $user->refresh();
        } elseif ($pending = $this->waitingSignUp($validated['email'])) {
            // The account is created only now, with its email already verified.
            $user = $registrations->complete($pending, $validated['otp']);
        } else {
            throw ValidationException::withMessages(['email' => 'We could not find a sign-up for this email address. Please sign up again.']);
        }

        // The code proves control of the inbox, so a buyer continues signed in, as on the website.
        if ($user->hasRole('buyer') && $user->isActive()) {
            return $this->issueToken($user, $validated['device_name'] ?? null, 'Email verified successfully.');
        }

        if ($user->hasRole('rider', 'courier') && ! $user->isActive()) {
            return response()->json(['message' => 'Email verified. Your rider application is now with '.($user->rider?->provider?->name ?? 'your logistics partner').' for review. You can sign in once it is approved.']);
        }

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resendVerification(Request $request, PendingRegistrationService $registrations): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])->first();

        if ($user?->hasVerifiedEmail()) {
            return response()->json(['message' => 'This email address is already verified.']);
        }

        $pending = $user ? null : $this->waitingSignUp($validated['email']);
        if (! $user && ! $pending) {
            throw ValidationException::withMessages(['email' => 'We could not find a sign-up for this email address. Please sign up again.']);
        }

        try {
            $user ? $user->sendEmailVerificationNotification() : $registrations->sendCode($pending);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'We could not send your code. Please try again shortly.'], 503);
        }

        return response()->json(['message' => 'A new verification code has been sent. Only the latest code will work.']);
    }
}
