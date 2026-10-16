<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Password sign-in and Sanctum token issuing shared by the buyer and rider
 * mobile controllers. Each check fails with its own message so a failed
 * sign-in shows exactly which rule stopped it.
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

        $user = User::where('email', $validated['email'])->first();
        if (! $user || ! filled($user->getAuthPassword()) || ! Hash::check($validated['password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages(['email' => 'Incorrect email or password.']);
        }

        if (! $user->hasRole($role)) {
            throw ValidationException::withMessages(['email' => $wrongRoleMessage]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => $this->inactiveAccountMessage($user)]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Verify your email address to continue.',
                'code' => 'unverified',
                'email' => $user->email,
            ], 403);
        }

        return $this->issueToken($user, $validated['device_name'] ?? null);
    }

    /** Why an inactive account cannot sign in; controllers can be more specific. */
    protected function inactiveAccountMessage(User $user): string
    {
        return 'This account is pending approval or has been suspended.';
    }

    protected function issueToken(User $user, ?string $deviceName, string $message = 'Signed in.'): JsonResponse
    {
        $token = $user->createToken(Str::limit($deviceName ?: 'ZAYLO mobile', 100, ''))->plainTextToken;

        return response()->json([
            'message' => $message,
            'token' => $token,
            'user' => MobilePayload::user($user),
        ]);
    }
}
