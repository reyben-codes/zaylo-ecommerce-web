<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\User as GoogleUser;

/**
 * Checks a Google ID token sent by the mobile app (Google Sign-In on the phone) and turns it into the same
 * identity the website's Socialite callback produces, so GoogleAccountService applies one set of rules.
 *
 * Google's tokeninfo endpoint checks the signature and expiry; here we also require that the token was issued to
 * this site's Google client (the app requests tokens for it as "serverClientId"), by Google, with a verified email.
 */
class GoogleIdTokenVerifier
{
    public const ENDPOINT = 'https://oauth2.googleapis.com/tokeninfo';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function configured(): bool
    {
        return $this->audiences() !== [];
    }

    /** The verified identity, or null when the token is invalid, expired, or meant for another app. */
    public function verify(string $idToken): ?GoogleUser
    {
        $response = Http::timeout(10)->connectTimeout(3)->get(self::ENDPOINT, ['id_token' => $idToken]);
        if (! $response->ok()) {
            return null;
        }
        $claims = $response->json();
        if (! is_array($claims)
            || ! in_array($claims['aud'] ?? null, $this->audiences(), true)
            || ! in_array($claims['iss'] ?? null, self::ISSUERS, true)
            || (int) ($claims['exp'] ?? 0) <= now()->timestamp
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === ''
            || ! is_string($claims['email'] ?? null)) {
            return null;
        }

        // tokeninfo returns booleans as strings.
        $claims['email_verified'] = in_array($claims['email_verified'] ?? null, [true, 'true'], true);

        return (new GoogleUser)->setRaw($claims)->map([
            'id' => $claims['sub'],
            'name' => $claims['name'] ?? null,
            'email' => $claims['email'],
            'avatar' => $claims['picture'] ?? null,
        ]);
    }

    /** Client IDs a token may be issued to: the website's, plus any extra app clients (GOOGLE_MOBILE_CLIENT_IDS). */
    private function audiences(): array
    {
        return array_values(array_filter(array_merge(
            [config('services.google.client_id')],
            array_map('trim', explode(',', (string) config('services.google.mobile_client_ids'))),
        )));
    }
}
