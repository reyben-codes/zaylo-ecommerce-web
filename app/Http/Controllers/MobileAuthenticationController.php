<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\IssuesMobileTokens;
use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Services\EmailVerificationService;
use App\Services\GoogleAccountService;
use App\Services\GoogleIdTokenVerifier;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

/**
 * Buyer sign-in, registration and email verification for the mobile app, plus
 * the logout and password-reset endpoints riders share.
 */
class MobileAuthenticationController extends Controller
{
    use IssuesMobileTokens;

    public function login(Request $request): JsonResponse
    {
        return $this->signInWithRole($request, 'buyer', 'This sign-in is for buyer accounts. Riders can use Login as a Rider; sellers can use the website.');
    }

    /**
     * "Continue with Google" in the buyer app. The app signs in with Google
     * natively and sends the ID token; accounts are found, linked or created
     * by the same rules as the website's Google sign-in (GoogleAccountService).
     */
    public function google(Request $request, GoogleIdTokenVerifier $verifier, GoogleAccountService $accounts): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        if (! GoogleIdTokenVerifier::configured()) {
            return response()->json([
                'message' => 'Google sign-in is not set up yet. Please use email and password for now.',
                'code' => 'google_unconfigured',
            ], 503);
        }

        try {
            $claims = $verifier->verify($validated['id_token']);
        } catch (\DomainException $exception) {
            $this->googleFailed($exception);
        } catch (\Throwable $exception) {
            // Google's signing keys could not be fetched; the token itself may be fine.
            Log::warning('Google sign-in key fetch failed.', ['exception_type' => $exception::class]);

            return response()->json(['message' => 'Google sign-in is unavailable right now. Please try again or use email and password.'], 503);
        }

        // Check the role before linking, so a seller's or rider's account is never changed from the buyer app.
        $existing = User::where('google_id', $claims['sub'])
            ->when(is_string($claims['email'] ?? null), fn ($query) => $query->orWhere('email', $claims['email']))
            ->get();
        if ($existing->contains(fn (User $user) => ! $user->hasRole('buyer'))) {
            throw ValidationException::withMessages(['google' => 'This sign-in is for buyer accounts. Riders can use Login as a Rider; sellers can use the website.']);
        }

        $identity = (new GoogleUser)->setRaw($claims)->map([
            'id' => $claims['sub'],
            'name' => $claims['name'] ?? null,
            'email' => $claims['email'] ?? null,
            'avatar' => $claims['picture'] ?? null,
        ]);
        try {
            $user = $accounts->resolve($identity, 'buyer');
        } catch (\DomainException $exception) {
            $this->googleFailed($exception);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['google' => 'This account is suspended. Please contact support.']);
        }

        return $this->issueToken($user, $validated['device_name'] ?? null);
    }

    private function googleFailed(\Throwable $exception): never
    {
        // Like the website: log only the exception type, never the token or Google's claims.
        Log::warning('Google sign-in failed.', ['exception_type' => $exception::class]);

        throw ValidationException::withMessages(['google' => 'We could not sign you in with Google. Please try again or use email and password.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => MobilePayload::user($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
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

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', new PersonName],
            'last_name' => ['required', 'string', 'max:100', new PersonName],
            'phone' => ['required', 'string', 'max:11', new PhoneNumber],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $user = DB::transaction(fn () => User::create([
            'name' => $validated['first_name'].' '.$validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'buyer',
            'phone' => $validated['phone'],
            'date_of_birth' => $validated['date_of_birth'],
            'status' => 'active',
        ]));

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'email' => $user->email,
                'verification_sent' => false,
                'message' => 'Your account was created, but we could not send the verification code. Please use Resend code.',
            ], 201);
        }

        return response()->json([
            'email' => $user->email,
            'verification_sent' => true,
            'message' => 'Your verification code has been sent.',
        ], 201);
    }

    public function verifyEmail(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ], [
            'otp.required' => 'Enter the verification code from your email.',
            'otp.regex' => 'Enter the six-digit code from your email.',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'We could not find an account for this email address.',
            ]);
        }

        $verification->verify($user, $validated['otp']);

        // The code proves control of the inbox, exactly as the web's signed-in
        // verification step does, so the buyer can continue without signing in again.
        if ($user->hasRole('buyer') && $user->isActive()) {
            return $this->issueToken($user->fresh(), $request->input('device_name'), 'Email verified successfully.');
        }

        if ($user->hasRole('rider') && ! $user->isActive()) {
            return response()->json(['message' => 'Email verified. Your rider application is now under review; we will email you once it is approved.']);
        }

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'We could not find an account for this email address.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'This email address is already verified.']);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'We could not send your code. Please try again shortly.',
            ], 503);
        }

        return response()->json(['message' => 'A new verification code has been sent.']);
    }
}
