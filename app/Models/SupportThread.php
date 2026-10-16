<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One user's conversation with the Zaylo admin team.
 */
class SupportThread extends Model
{
    protected $fillable = ['user_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    public function isOpenTo(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id || $user->hasRole('admin');
    }

    /** Unread for the viewer: the user reads admin replies; admins read the user's messages. */
    public function unreadFor(User $viewer): int
    {
        return $this->messages()->whereNull('read_at')
            ->when(
                $viewer->hasRole('admin'),
                fn ($q) => $q->where('sender_id', $this->user_id),
                fn ($q) => $q->where('sender_id', '!=', $viewer->id),
            )
            ->count();
    }
}
