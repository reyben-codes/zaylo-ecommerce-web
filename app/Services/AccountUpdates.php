<?php

namespace App\Services;

use App\Models\AccountUpdate;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AccountUpdates
{
    public function send(?int $userId, string $event, string $section, string $title, string $body, string $kind = 'wallet', int $entityId = 0): void
    {
        if (! $userId || ! Schema::hasTable('account_updates')) return;
        $update = AccountUpdate::firstOrCreate(['user_id' => $userId, 'event_key' => $event], [
            'section' => $section, 'title' => $title, 'body' => $body, 'kind' => $kind, 'entity_id' => $entityId,
        ]);
        if ($update->wasRecentlyCreated) {
            app(MobilePushNotifications::class)->queue($userId, $event, $title, $body, $kind, $entityId);
        }
    }

    public function counts(User $user): array
    {
        if (! Schema::hasTable('account_updates')) return [];
        return $this->unreadQuery($user)
            ->selectRaw('section, COUNT(*) AS total')->groupBy('section')->pluck('total', 'section')->map(fn ($n) => (int) $n)->all();
    }

    private function unreadQuery(User $user)
    {
        $query = AccountUpdate::where('user_id', $user->id)->whereNull('read_at');
        if (Schema::hasTable('buyer_notification_reads')) {
            $readIds = DB::table('buyer_notification_reads')->where('user_id', $user->id)->where('notification_key', 'like', 'account:%')
                ->pluck('notification_key')->map(fn ($key) => (int) substr($key, 8))->all();
            $query->whereNotIn('id', $readIds);
            $historyPrefix = \App\Models\Order::usesLegacySchema() ? 'order:' : 'history:';
            $historyKeys = DB::table('buyer_notification_reads')->where('user_id', $user->id)->where('notification_key', 'like', $historyPrefix.'%')
                ->pluck('notification_key')->map(fn ($key) => 'order-history-'.substr($key, strlen($historyPrefix)))->all();
            $query->whereNotIn('event_key', $historyKeys);
        }
        return $query;
    }

    public function feed(User $user): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('account_updates')) return collect();
        $legacy = \App\Models\Order::usesLegacySchema();
        return AccountUpdate::where('user_id', $user->id)->when($legacy, fn ($q) => $q->where('event_key', 'not like', 'order-history-%'))
            ->latest('id')->limit(50)->get()->map(function ($update) use ($user) {
            $route = $user->hasRole('buyer') ? 'buyer.orders' : ($user->hasRole('seller') ? ($update->section === 'orders' ? 'seller.orders' : 'seller.wallet') : ($user->hasRole('admin') ? 'admin.seller-withdrawals' : 'logistics.earnings'));
            return ['key' => str_starts_with($update->event_key, 'order-history-') ? 'history:'.substr($update->event_key, 14) : 'account:'.$update->id, 'type' => $update->kind === 'order' ? 'order' : 'account',
                'icon' => 'fa-money-bill-transfer', 'title' => $update->title, 'message' => $update->body,
                'url' => \Illuminate\Support\Facades\Route::has($route) ? route($route) : url('/'), 'action' => 'View details',
                'at' => $update->created_at, 'seen' => $update->read_at !== null, 'urgent' => false];
        });
    }

    public function read(User $user, ?string $section = null, ?int $through = null): void
    {
        if (! Schema::hasTable('account_updates') || $through === null) return;
        AccountUpdate::where('user_id', $user->id)->whereNull('read_at')->where('id', '<=', $through)
            ->when($section && $section !== 'notifications', fn ($q) => $q->where('section', $section))->update(['read_at' => now()]);
    }

    public function payload(User $user): array
    {
        $items = Schema::hasTable('account_updates')
            ? AccountUpdate::where('user_id', $user->id)->latest('id')->limit(50)->get()
            : collect();
        $counts = $this->counts($user);
        $unreadIds = Schema::hasTable('account_updates') ? array_fill_keys($this->unreadQuery($user)->pluck('id')->all(), true) : [];
        return ['counts' => $counts, 'unread' => array_sum($counts), 'through' => $items->max('id') ?? 0,
            'updates' => $items->map(fn ($item) => [
                'id' => $item->id, 'title' => $item->title, 'body' => $item->body, 'section' => $item->section,
                'kind' => $item->kind, 'entity_id' => $item->entity_id, 'read' => ! isset($unreadIds[$item->id]),
                'created_at' => $item->created_at->toIso8601String(),
            ])->values()];
    }

    public function admins(string $event, string $title, string $body): void
    {
        User::where('status', 'active')->where(function ($q) {
            Schema::hasColumn('users', 'role') ? $q->where('role', 'admin') : $q->whereHas('roles', fn ($role) => $role->where('name', 'admin'));
        })->pluck('id')->each(fn ($id) => $this->send($id, $event, 'payouts', $title, $body));
    }
}
