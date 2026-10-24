<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A mobile app sign-in. The app sends "Authorization: Bearer {id}|{secret}"; only a SHA-256 hash of the secret
 * is stored. Same table and format as Laravel Sanctum's personal access tokens.
 */
class MobileToken extends Model
{
    protected $table = 'personal_access_tokens';

    protected $fillable = ['name', 'token', 'abilities', 'expires_at'];

    protected $hidden = ['token'];

    protected $casts = ['abilities' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The token for a bearer value, or null when it is unknown, wrong or expired. */
    public static function findForBearer(?string $bearer): ?self
    {
        if (! $bearer || ! str_contains($bearer, '|')) {
            return null;
        }
        [$id, $secret] = explode('|', $bearer, 2);
        if (! ctype_digit($id) || $secret === '') {
            return null;
        }

        $token = static::find((int) $id);
        if (! $token || ! hash_equals($token->token, hash('sha256', $secret))) {
            return null;
        }

        return $token->expires_at?->isPast() ? null : $token;
    }
}
