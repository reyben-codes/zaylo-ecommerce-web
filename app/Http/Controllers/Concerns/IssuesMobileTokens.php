<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PendingRegistrationService;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Password sign-in and token issuing shared by the buyer and rider mobile controllers. Each check fails with
 * its own message, so the app can say exactly why a sign-in was refused.
 */
trait IssuesMobileTokens
{
    protected function signInWithRole(Request $request, string $role, string $wrongRoleMessage): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])->first();
        if (! $user || ! filled($user->getAuthPassword()) || ! Hash::check($validated['password'], $user->getAuthPassword())) {
            if ($user) {
                app(ActivityLogger::class)->record('auth.failed', 'auth', 'Failed sign-in attempt in the mobile app (wrong password)', $user);
            } elseif ($this->waitingSignUp($validated['email'], $validated['password'])) {
                // Signed up but the emailed code was never entered: the app opens its code screen.
                return $this->unverifiedResponse($validated['email']);
            }

            throw ValidationException::withMessages(['email' => 'Incorrect email or password.']);
        }

        if (! $user->hasRole($role, ...($role === 'rider' ? ['courier'] : []))) {
            throw ValidationException::withMessages(['email' => $wrongRoleMessage]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => $this->inactiveAccountMessage($user)]);
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->unverifiedResponse($user->email);
        }

        return $this->issueToken($user, $validated['device_name'] ?? null);
    }

    /** Parks the sign-up until its emailed code is entered, then emails the code. */
    protected function startSignUp(Request $request, PendingRegistrationService $registrations, array $validated, string $sentMessage = 'Your verification code has been sent.', array $files = []): JsonResponse
    {
        if (! $registrations->available()) {
            abort_if($files !== [], 503, 'Rider sign-up from the app needs the latest database update. Please try again later.');
            // Until the pending_registrations migration runs, the account is saved first, as on the website.
            $user = $registrations->createImmediately($request, $validated, null);
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $exception) {
                report($exception);

                return response()->json(['email' => $user->email, 'verification_sent' => false, 'message' => 'Your account was saved, but we could not send your code. Please use Resend code.'], 201);
            }

            return response()->json(['email' => $user->email, 'verification_sent' => true, 'message' => $sentMessage], 201);
        }

        $pending = $registrations->begin($request, $validated, null, $files);
        try {
            $registrations->sendCode($pending, first: true);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['email' => $pending->email, 'verification_sent' => false, 'message' => 'We could not send your code. Your account has not been created yet; use Resend code to try again.'], 201);
        }

        return response()->json(['email' => $pending->email, 'verification_sent' => true, 'message' => $sentMessage], 201);
    }

    /** Why an inactive account cannot sign in; controllers can be more specific. */
    protected function inactiveAccountMessage(User $user): string
    {
        return 'This account is pending approval or has been suspended.';
    }

    protected function issueToken(User $user, ?string $deviceName, string $message = 'Signed in.'): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'ZAYLO mobile');
        app(ActivityLogger::class)->record('mobile.login', 'auth', 'Signed in to the mobile app', $user, null, array_filter(['device' => $deviceName]));

        return response()->json([
            'message' => $message,
            'token' => $token,
            'user' => MobilePayload::user($user),
        ]);
    }

    protected function unverifiedResponse(string $email): JsonResponse
    {
        return response()->json([
            'message' => 'Verify your email address to continue.',
            'code' => 'unverified',
            'email' => $email,
        ], 403);
    }

    /** A sign-up still waiting for its emailed code, when the password matches. */
    protected function waitingSignUp(string $email, ?string $password = null): ?PendingRegistration
    {
        if (! Schema::hasTable('pending_registrations')) {
            return null;
        }
        $pending = PendingRegistration::whereRaw('LOWER(email) = ?', [Str::lower($email)])->where('expires_at', '>', now())->latest('id')->first();
        if ($pending && $password !== null && ! Hash::check($password, $pending->payload['password_hash'] ?? '')) {
            return null;
        }

        return $pending;
    }
}
