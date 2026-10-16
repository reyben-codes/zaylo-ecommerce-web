<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\IssuesMobileTokens;
use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Services\EmailVerificationService;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

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
