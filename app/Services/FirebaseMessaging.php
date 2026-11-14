<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** FCM HTTP v1. Server credentials and device tokens must never appear in logs. */
class FirebaseMessaging
{
    public function configured(): bool
    {
        return filled(config('push.project_id')) && filled(config('push.credentials')) && is_readable(config('push.credentials'));
    }

    public function validateConfiguration(): void
    {
        $this->credentials();
    }

    /** @return string sent, unregistered, invalid or retry */
    public function send(string $deviceToken, string $title, string $body, array $data, string $tag): string
    {
        $response = Http::withToken($this->accessToken())->acceptJson()->connectTimeout(5)->timeout(12)
            ->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode(config('push.project_id')).'/messages:send', [
                'message' => [
                    'fid' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'high', 'ttl' => '43200s',
                        'notification' => ['channel_id' => config('push.channel'), 'icon' => 'ic_stat_zaylo', 'tag' => $tag],
                    ],
                ],
            ]);
        if ($response->successful()) return 'sent';
        $codes = collect($response->json('error.details', []))->pluck('errorCode');
        if ($codes->contains('UNREGISTERED')) return 'unregistered';
        if ($response->status() === 400) return 'invalid';
        if ($response->status() === 401) Cache::forget($this->cacheKey());

        return 'retry-http-'.$response->status();
    }

    private function credentials(): array
    {
        if (! $this->configured()) throw new \RuntimeException('Firebase server credential is not configured or readable.');
        $credentials = json_decode(file_get_contents(config('push.credentials')), true);
        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])
            || ($credentials['project_id'] ?? null) !== config('push.project_id')) {
            throw new \RuntimeException('Firebase server credential is invalid or belongs to another project.');
        }
        if (! openssl_pkey_get_private($credentials['private_key'])) {
            throw new \RuntimeException('Firebase credential contains an invalid private key.');
        }

        return $credentials;
    }

    private function cacheKey(): string
    {
        $credentials = $this->credentials();
        return 'firebase-access:'.hash('sha256', $credentials['client_email'].'|'.($credentials['private_key_id'] ?? ''));
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        return Cache::remember($this->cacheKey(), now()->addMinutes(45), function () use ($credentials) {
            $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $jwt = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode([
                'iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600,
            ]));
            if (! openssl_sign($jwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Firebase credential signing failed.');
            }
            $response = Http::asForm()->connectTimeout(5)->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt.'.'.$encode($signature),
            ]);
            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new \RuntimeException('Firebase server authentication failed.');
            }

            return $token;
        });
    }
}
