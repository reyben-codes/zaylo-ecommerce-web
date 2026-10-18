<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Checks a Google ID token from the mobile app's native Google sign-in: its
 * RS256 signature against Google's published keys, the issuer, that it was
 * issued to one of ZAYLO's OAuth clients, and that it has not expired.
 *
 * @see https://developers.google.com/identity/sign-in/android/backend-auth
 */
class GoogleIdTokenVerifier
{
    public const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    private const CACHE_KEY = 'google-id-token-certs';

    /** Seconds of clock difference tolerated between this server and Google. */
    private const LEEWAY = 60;

    /** OAuth client IDs whose tokens are accepted: the web client the app names as serverClientId, plus any iOS client. */
    public static function audiences(): array
    {
        return array_values(array_filter(array_map('trim', [
            (string) config('services.google.client_id'),
            ...explode(',', (string) config('services.google.mobile_client_ids')),
        ])));
    }

    public static function configured(): bool
    {
        return self::audiences() !== [];
    }

    /**
     * Returns the token's claims (sub, email, email_verified, name, picture, ...).
     *
     * @throws \DomainException when the token is not a valid Google ID token for ZAYLO
     */
    public function verify(string $idToken): array
    {
        try {
            $claims = $this->decode($idToken, $this->certs());
        } catch (\UnexpectedValueException $exception) {
            // A key we have not seen yet usually means Google rotated its keys: fetch them once more.
            if (! str_contains($exception->getMessage(), '"kid"')) {
                throw new \DomainException('Invalid Google ID token.', previous: $exception);
            }
            try {
                $claims = $this->decode($idToken, $this->certs(refresh: true));
            } catch (\UnexpectedValueException $retry) {
                throw new \DomainException('Invalid Google ID token.', previous: $retry);
            }
        }

        if (! in_array($claims['iss'] ?? null, self::ISSUERS, true)) {
            throw new \DomainException('Google ID token has the wrong issuer.');
        }
        $audience = (array) ($claims['aud'] ?? []);
        if (array_intersect($audience, self::audiences()) === []) {
            throw new \DomainException('Google ID token was issued to another app.');
        }
        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new \DomainException('Google ID token has no subject.');
        }

        return $claims;
    }

    private function decode(string $idToken, array $certs): array
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY;
        try {
            // parseKeySet only accepts the algorithms Google publishes (RS256), so "none" and HS256 tokens fail.
            $payload = JWT::decode($idToken, JWK::parseKeySet($certs, 'RS256'));
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        return json_decode(json_encode($payload), true);
    }

    /** Google's signing keys, cached for as long as Google's Cache-Control allows. */
    private function certs(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = Http::acceptJson()->connectTimeout(3)->timeout(10)->get(self::CERTS_URL);
        if (! $response->successful() || ! is_array($response->json('keys'))) {
            throw new \RuntimeException('Google signing keys are unavailable.');
        }
        preg_match('/max-age=(\d+)/', (string) $response->header('Cache-Control'), $maxAge);
        Cache::put(self::CACHE_KEY, $response->json(), now()->addSeconds(min((int) ($maxAge[1] ?? 3600), 86400)));

        return $response->json();
    }
}
