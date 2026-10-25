<?php

namespace App\Models\Concerns;

use App\Models\MobileToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/** Mobile app sign-in tokens for a user (see App\Models\MobileToken and the "mobile" auth guard). */
trait HasMobileTokens
{
    private ?MobileToken $accessToken = null;

    public function tokens(): MorphMany
    {
        return $this->morphMany(MobileToken::class, 'tokenable');
    }

    /** Creates a token and returns the plain "{id}|{secret}" value, which is shown only this once. */
    public function createToken(string $name): string
    {
        $secret = Str::random(40);
        $days = (int) config('mobile.token_lifetime_days', 0);
        $token = $this->tokens()->create([
            'name' => Str::limit($name, 100, ''),
            'token' => hash('sha256', $secret),
            'expires_at' => $days > 0 ? now()->addDays($days) : null,
        ]);

        return $token->getKey().'|'.$secret;
    }

    /** The token the current API request signed in with, if any. */
    public function currentAccessToken(): ?MobileToken
    {
        return $this->accessToken;
    }

    public function withAccessToken(?MobileToken $token): static
    {
        $this->accessToken = $token;

        return $this;
    }
}
