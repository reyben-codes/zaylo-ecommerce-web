<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A conversation between a logistics partner and one seller or rider.
 */
class LogisticsThread extends Model
{
    protected $fillable = ['logistics_provider_id', 'user_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    /** The seller or rider on the other side. */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LogisticsMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(LogisticsMessage::class)->latestOfMany();
    }

    /** The provider's owner, or the seller/rider in it. */
    public function isOpenTo(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id || (int) $this->provider?->user_id === (int) $user->id;
    }

    public function unreadFor(User $user): int
    {
        return $this->messages()->whereNull('read_at')->where('sender_id', '!=', $user->id)->count();
    }
}
