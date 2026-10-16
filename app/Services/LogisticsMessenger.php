<?php

namespace App\Services;

use App\Models\LogisticsMessage;
use App\Models\LogisticsProvider;
use App\Models\LogisticsThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Messages between a logistics partner and its sellers and riders.
 */
class LogisticsMessenger
{
    public static function available(): bool
    {
        return Schema::hasTable('logistics_threads') && Schema::hasTable('logistics_messages');
    }

    public function threadFor(LogisticsProvider $provider, User $participant): LogisticsThread
    {
        return LogisticsThread::firstOrCreate(['logistics_provider_id' => $provider->id, 'user_id' => $participant->id]);
    }

    public function send(LogisticsThread $thread, User $sender, string $body): LogisticsMessage
    {
        return DB::transaction(function () use ($thread, $sender, $body) {
            $message = $thread->messages()->create(['sender_id' => $sender->id, 'body' => $body]);
            $thread->update(['last_message_at' => $message->created_at]);

            return $message;
        });
    }

    /** A note from the partner to a seller or rider, e.g. about a pickup. Quietly skipped before the chat tables exist. */
    public function notify(LogisticsProvider $provider, ?int $participantId, string $body): void
    {
        $participant = $participantId ? User::find($participantId) : null;
        if (! self::available() || ! $participant || ! $provider->owner) {
            return;
        }
        $this->send($this->threadFor($provider, $participant), $provider->owner, $body);
    }

    public function markRead(LogisticsThread $thread, User $reader): void
    {
        $thread->messages()->whereNull('read_at')->where('sender_id', '!=', $reader->id)->update(['read_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        if (! self::available()) {
            return 0;
        }

        return LogisticsMessage::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->whereHas('thread', fn ($thread) => $thread->where('user_id', $user->id)
                ->orWhereHas('provider', fn ($provider) => $provider->where('user_id', $user->id)))
            ->count();
    }

    public function payload(LogisticsMessage $message, User $viewer): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => (int) $message->sender_id === (int) $viewer->id,
            'sender' => $message->sender?->name,
            'time' => $message->created_at?->timezone('Asia/Manila')->format('M j, g:i A'),
        ];
    }
}
