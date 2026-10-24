<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Two\User as GoogleUser;

class GoogleAccountService
{
    // Riders and logistics partners register with the form: they must name a partner or company.
    public const PUBLIC_ROLES = ['buyer', 'seller'];

    public function resolve(GoogleUser $identity, string $role): User
    {
        $attributes = [
            'google_id' => $identity->getId(),
            'email' => $identity->getEmail(),
            'name' => $identity->getName(),
        ];
        $verifiedEmail = $identity->user['email_verified'] ?? $identity->user['verified_email'] ?? false;
        if ($verifiedEmail !== true || ! in_array($role, self::PUBLIC_ROLES, true)
            || Validator::make($attributes, [
                'google_id' => 'required|string|max:255',
                'email' => 'required|string|email:rfc|max:255',
                'name' => 'nullable|string|max:255',
            ])->fails()) {
            throw new \DomainException('Unusable Google identity.');
        }

        $avatar = $identity->getAvatar();
        $avatar = is_string($avatar) && strlen($avatar) <= 255
            && filter_var($avatar, FILTER_VALIDATE_URL) && parse_url($avatar, PHP_URL_SCHEME) === 'https'
            ? $avatar : null;

        // Unique email and google_id indexes arbitrate simultaneous first-time callbacks.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                [$user, $newlyVerified] = DB::transaction(function () use ($attributes, $role, $avatar) {
                    $byId = User::where('google_id', $attributes['google_id'])->lockForUpdate()->first();
                    $byEmail = User::where('email', $attributes['email'])->lockForUpdate()->first();
                    if (($byId && $byEmail && ! $byId->is($byEmail))
                        || ($byEmail && $byEmail->email !== $attributes['email'])
                        || ($byEmail?->google_id && $byEmail->google_id !== $attributes['google_id'])) {
                        throw new \DomainException('Conflicting Google account link.');
                    }

                    $user = $byId ?? $byEmail;
                    // Each sign-in page admits one account type; never link or sign in another type's account here.
                    if ($user && User::portalOf($user->role) !== User::portalOf($role)) {
                        throw new \App\Exceptions\WrongAccountTypeException($user->role);
                    }
                    $new = ! $user;
                    if ($new) {
                        $user = new User;
                        $user->forceFill([
                            'name' => $attributes['name'] ?: $attributes['email'],
                            'email' => $attributes['email'],
                            'password' => null,
                            'role' => $role === 'courier' ? 'rider' : $role,
                            'status' => $role === 'buyer' ? 'active' : 'pending',
                            'auth_provider' => 'google',
                        ]);
                    }

                    $newlyVerified = ! $user->hasVerifiedEmail();
                    // A saved Google ID remains the primary identity even if its email changes.
                    // Never verify an unrelated local email or overwrite the account email.
                    if ($newlyVerified && $user->email !== $attributes['email']) {
                        throw new \DomainException('Google email does not verify the account email.');
                    }
                    $user->forceFill(['google_id' => $attributes['google_id']]);
                    // The Google photo fills in until the user uploads their own; it never replaces an upload.
                    if ($avatar !== null && ! $user->hasUploadedAvatar()) {
                        $user->avatar = $avatar;
                    }
                    if ($newlyVerified) {
                        $user->email_verified_at = now();
                    }
                    $user->save();

                    if ($new && $role === 'seller') {
                        $user->openSellerShop($user->name."'s Store");
                    }

                    return [$user, $newlyVerified];
                }, 3);
                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 1) {
                    throw $exception;
                }
            }
        }

        if ($newlyVerified) {
            event(new Verified($user));
        }

        return $user;
    }
}
