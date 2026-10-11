<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** A rider's chat with ZAYLO support (one per rider). */
class SupportConversation extends Model
{
    protected $fillable = ['user_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    /**
     * Adds a message; a repeated [$clientId] returns the existing one, so a
     * send retried after a dropped connection is not stored twice.
     */
    public function post(User $sender, string $body, bool $fromStaff, ?string $clientId = null): SupportMessage
    {
        $message = $this->messages()->firstOrCreate(
            ['client_id' => $clientId ?: (string) Str::uuid()],
            ['sender_id' => $sender->id, 'from_staff' => $fromStaff, 'body' => trim($body)],
        );
        if ($message->wasRecentlyCreated) {
            $this->update(['last_message_at' => $message->created_at]);
        }

        return $message;
    }

    /** Marks the other side's messages as read by [$reader]'s side. */
    public function markReadBy(bool $staff): void
    {
        $this->messages()->where('from_staff', ! $staff)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function unreadFor(bool $staff): int
    {
        return $this->messages()->where('from_staff', ! $staff)->whereNull('read_at')->count();
    }
}
