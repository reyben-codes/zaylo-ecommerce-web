<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MobilePushDevice extends Model
{
    protected $guarded = [];
    protected $hidden = ['token', 'token_hash'];
    protected $casts = ['token' => 'encrypted'];

    public function scopeActive(Builder $query): void
    {
        $query->whereHas('session', fn ($sessions) => $sessions
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereColumn('tokenable_id', 'mobile_push_devices.user_id')
            ->where(fn ($expires) => $expires->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->whereHas('user', fn ($users) => $users->where('status', 'active'));
    }

    public function user() { return $this->belongsTo(User::class); }
    public function session() { return $this->belongsTo(MobileToken::class, 'mobile_token_id'); }
}
