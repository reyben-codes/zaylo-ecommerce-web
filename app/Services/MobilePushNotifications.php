<?php

namespace App\Services;

use App\Models\MobilePushDevice;
use App\Models\MobilePushMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MobilePushNotifications
{
    /** Persist with the domain transaction; send only after commit and after the HTTP response. */
    public function queue(?int $userId, string $event, string $title, string $body, string $kind, int $entityId = 0, ?int $mobileTokenId = null): void
    {
        if (! $userId || ! Schema::hasTable('mobile_push_devices')) return;
        $devices = MobilePushDevice::active()->where('user_id', $userId)
            ->when($mobileTokenId, fn ($query) => $query->where('mobile_token_id', $mobileTokenId))->get();
        if ($devices->isEmpty()) return;
        foreach ($devices as $device) {
            MobilePushMessage::firstOrCreate(['device_id' => $device->id, 'event_key' => $event], [
                'title' => $title, 'body' => $body,
                'data' => ['user_id' => (string) $userId, 'kind' => $kind, 'entity_id' => (string) $entityId],
                'available_at' => now(),
            ]);
        }
        DB::afterCommit(fn () => app()->terminating(function () {
            $this->drain(10);
        }));
    }

    public function drain(int $limit = 100): int
    {
        if (! Schema::hasTable('mobile_push_messages')) return 0;
        $messaging = app(FirebaseMessaging::class);
        if (! $messaging->configured()) {
            if (MobilePushMessage::whereNull('sent_at')->whereNull('failed_at')->exists()
                && Cache::add('push:missing-config-warning', true, 1800)) {
                Log::warning('Mobile push delivery is waiting for a readable Firebase credential and project configuration.');
            }
            return 0;
        }
        $sent = 0;
        $ids = MobilePushMessage::whereNull('sent_at')->whereNull('failed_at')->where('available_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<=', now()))
            ->oldest('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $message = DB::transaction(function () use ($id) {
                $message = MobilePushMessage::whereKey($id)->lockForUpdate()->first();
                if (! $message || $message->sent_at || $message->failed_at || $message->locked_until?->isFuture()) return null;
                if ($message->created_at->lt(now()->subHours(12))) {
                    $message->update(['failed_at' => now()]);
                    return null;
                }
                $message->update(['locked_until' => now()->addMinutes(3), 'attempts' => $message->attempts + 1]);
                return $message;
            });
            if (! $message) continue;
            $device = MobilePushDevice::active()->whereKey($message->device_id)->first();
            if (! $device) {
                $message->update(['failed_at' => now(), 'locked_until' => null]);
                continue;
            }
            try {
                $result = $messaging->send($device->token, $message->title, $message->body,
                    $message->data + ['notification_id' => 'zaylo-'.$message->id], 'zaylo-'.$message->id);
            } catch (\RuntimeException $exception) {
                $result = 'retry-auth-or-transport';
            } catch (\Throwable) {
                // Never report transport exceptions: they can contain credential/device-token payloads.
                $result = 'retry';
            }
            if ($result === 'unregistered') {
                MobilePushDevice::whereKey($device->id)->where('token_hash', $device->token_hash)->delete();
                continue;
            }
            if ($result === 'sent') {
                $message->update(['sent_at' => now(), 'locked_until' => null]);
                $sent++;
            } elseif ($result === 'invalid' || $message->attempts >= 5) {
                $message->update(['failed_at' => now(), 'locked_until' => null]);
                Log::warning('Mobile push could not be delivered.', ['message_id' => $message->id, 'attempts' => $message->attempts, 'provider_result' => $result]);
            } else {
                if ($message->attempts === 1) Log::warning('Mobile push delivery will retry.', ['message_id' => $message->id, 'provider_result' => $result]);
                $message->update(['available_at' => now()->addMinutes(min(30, 2 ** $message->attempts)), 'locked_until' => null]);
            }
        }
        // Retain delivery metadata briefly, rather than allowing the outbox to grow indefinitely.
        MobilePushMessage::where('created_at', '<', now()->subDays(7))->delete();

        return $sent;
    }
}
