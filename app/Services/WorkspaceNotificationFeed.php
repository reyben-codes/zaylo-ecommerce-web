<?php

namespace App\Services;

use App\Http\Controllers\SellerController;
use App\Models\ActivityLog;
use App\Models\ComplianceWarning;
use App\Models\Dispute;
use App\Models\IdentityVerification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications for the Admin, Seller and Logistics Center sidebars, built from what needs attention right now (like the
 * buyer feed) plus account decisions taken from the activity log. Read state is kept in buyer_notification_reads, keyed
 * per user, so it works for every role. Items marked "urgent" are warnings about the seller's account or products and
 * also show as a banner.
 */
class WorkspaceNotificationFeed
{
    private const LIMIT = 40;

    /** Admin decisions shown to every admin (and, for account changes, to the account owner). */
    private const DECISION_EVENTS = [
        'admin.users.status', 'admin.identity.review', 'admin.compliance.review', 'admin.compliance.warn', 'admin.compliance.toggle',
    ];

    /** Built once per request (the sidebar badge, banner and page share it), so it is kept on the request. */
    public function forUser(User $user): Collection
    {
        $attributes = request()->attributes;
        $key = 'workspaceNotifications.'.$user->id;
        if (! $attributes->has($key)) {
            $attributes->set($key, $this->withReadState($user, match (true) {
                $user->hasRole('admin') => $this->admin($user),
                $user->hasRole('seller') => $this->seller($user),
                $user->hasRole('logistics') => $this->logistics($user),
                default => collect(),
            }));
        }

        return $attributes->get($key);
    }

    public function unreadCount(User $user): int
    {
        return $this->forUser($user)->where('read', false)->count();
    }

    /** Unread warnings about the seller's account or products. */
    public function urgent(User $user): Collection
    {
        return $this->forUser($user)->where('read', false)->where('urgent', true)->values();
    }

    public function markRead(User $user, Collection $keys): void
    {
        if (! Schema::hasTable('buyer_notification_reads')) {
            return;
        }

        $keys->unique()->map(fn (string $key) => ['user_id' => $user->id, 'notification_key' => $key, 'read_at' => now()])
            ->chunk(200)->each(fn ($chunk) => DB::table('buyer_notification_reads')->insertOrIgnore($chunk->values()->all()));
        request()->attributes->remove('workspaceNotifications.'.$user->id);
    }

    private function admin(User $viewer): Collection
    {
        $items = $this->decisionHistory($viewer);

        $this->pendingApplications()->latest()->limit(self::LIMIT)->get()->each(fn (User $applicant) => $items->push($this->item(
            'application:'.$applicant->id, 'account', 'fa-user-check', 'New '.($applicant->hasRole('logistics') ? 'logistics partner' : 'seller').' application',
            $applicant->name.' is waiting for approval.', route('admin.registrations'), 'Review application', $applicant->created_at,
        )));

        if (Schema::hasTable('identity_verifications')) {
            IdentityVerification::with('user')->where('status', 'pending')->latest()->limit(self::LIMIT)->get()
                ->each(fn (IdentityVerification $check) => $items->push($this->item(
                    'identity:'.$check->id, 'account', 'fa-id-card', 'ID to check',
                    ($check->user?->name ?? 'A buyer').' uploaded an ID for verification.', route('admin.identity'), 'Check ID', $check->created_at,
                )));
        }

        if (Product::usesReview()) {
            Product::where('review_status', Product::REVIEW_PENDING)->notArchived()->latest('updated_at')->limit(self::LIMIT)->get(['id', 'name', 'updated_at'])
                ->each(fn (Product $product) => $items->push($this->item(
                    'listing:'.$product->id.':'.$product->updated_at?->timestamp, 'product', 'fa-box-open', 'Listing to approve',
                    $product->name.' is waiting for review.', route('admin.compliance', ['view' => 'pending']), 'Review listing', $product->updated_at,
                )));
        }

        if (Schema::hasTable('dispute_messages')) {
            Dispute::whereIn('status', ['open', 'in_review'])->latest('updated_at')->limit(self::LIMIT)->get()
                ->each(fn (Dispute $dispute) => $items->push($this->item(
                    'dispute:'.$dispute->id.':'.$dispute->status, 'order', 'fa-scale-balanced', $dispute->status === 'open' ? 'New complaint' : 'Complaint in review',
                    'Complaint #'.$dispute->id.' needs a decision.', route('admin.disputes.show', $dispute), 'Open complaint', $dispute->updated_at,
                )));
        }

        if (Schema::hasTable('support_messages')) {
            // The newest unread message from each user starts one notification.
            SupportMessage::with('thread.user')->whereNull('read_at')
                ->whereHas('thread', fn (Builder $thread) => $thread->whereColumn('support_threads.user_id', 'support_messages.sender_id'))
                ->latest('id')->limit(200)->get()->unique('support_thread_id')->take(self::LIMIT)
                ->each(fn (SupportMessage $message) => $items->push($this->item(
                    'support:'.$message->id, 'message', 'fa-comments', 'Support message',
                    ($message->thread?->user?->name ?? 'A user').': '.(filled($message->body) ? str($message->body)->limit(90) : 'sent a photo'), route('admin.chat', ['thread' => $message->support_thread_id]), 'Reply', $message->created_at,
                )));
        }

        return $items;
    }

    private function seller(User $seller): Collection
    {
        $items = $this->accountDecisions($seller, route('seller.account'));
        $shopId = Product::usesLegacySchema() ? $seller->id : $seller->sellerShop()?->id;
        $cutoff = now()->subDays(30);

        if (Schema::hasTable('compliance_warnings')) {
            ComplianceWarning::with('product')->where('seller_id', $seller->id)
                ->where(fn (Builder $query) => $query->whereNull('acknowledged_at')->orWhere('created_at', '>=', $cutoff))
                ->latest()->limit(self::LIMIT)->get()
                ->each(fn (ComplianceWarning $warning) => $items->push($this->item(
                    'warning:'.$warning->id, 'warning', 'fa-triangle-exclamation', 'Policy warning: '.$warning->reason_label,
                    ($warning->product ? $warning->product->name.' · ' : '').($warning->action === 'product_hidden' ? 'Hidden from buyers until fixed. ' : '').$warning->details,
                    route('seller.dashboard'), 'View warning', $warning->created_at, urgent: $warning->acknowledged_at === null,
                )));
        }

        if (! $shopId) {
            return $items;
        }

        if (Product::usesReview()) {
            Product::where('seller_id', $shopId)->notArchived()->whereIn('review_status', [Product::REVIEW_APPROVED, Product::REVIEW_REJECTED])
                ->whereNotNull('reviewed_at')->where('reviewed_at', '>=', $cutoff)->latest('reviewed_at')->limit(self::LIMIT)->get()
                ->each(function (Product $product) use ($items) {
                    $rejected = $product->is_rejected;
                    $items->push($this->item(
                        'review:'.$product->id.':'.$product->review_status.':'.$product->reviewed_at->timestamp, 'product', $rejected ? 'fa-circle-xmark' : 'fa-circle-check',
                        $rejected ? 'Listing not approved' : 'Listing approved',
                        $rejected ? $product->name.' was not approved.'.($product->review_note ? ' Reason: '.$product->review_note : ' Edit it and submit again.') : $product->name.' is now live for buyers.',
                        route('seller.products.edit', $product), $rejected ? 'Fix listing' : 'View listing', $product->reviewed_at, urgent: $rejected,
                    ));
                });
        }

        foreach (['out' => 'Out of stock', 'low' => 'Low stock'] as $state => $title) {
            SellerController::whereStock(Product::where('seller_id', $shopId)->notArchived()->where('is_active', true), $state)
                ->latest('updated_at')->limit(self::LIMIT)->get(['id', 'name', 'updated_at'])
                ->each(fn (Product $product) => $items->push($this->item(
                    'stock:'.$product->id.':'.$state.':'.$product->updated_at?->timestamp, 'stock', $state === 'out' ? 'fa-box' : 'fa-boxes-stacked', $title,
                    $state === 'out' ? $product->name.' has sold out. Buyers cannot order it until you restock.' : $product->name.' is running low. Restock soon.',
                    route('seller.inventory'), 'Update stock', $product->updated_at ?? now(), urgent: $state === 'out',
                )));
        }

        $newOrders = Order::usesLegacySchema()
            ? Order::where('seller_id', $shopId)->where('status', 'placed')->latest()->limit(self::LIMIT)->get()
                ->map(fn (Order $order) => [$order->id, $order->order_number, $order->created_at])
            : SellerOrder::with('order')->where('seller_id', $shopId)->where('status', 'pending')->latest()->limit(self::LIMIT)->get()
                ->map(fn (SellerOrder $order) => [$order->id, $order->order?->reference ?? '#'.$order->id, $order->created_at]);
        $newOrders->each(fn (array $order) => $items->push($this->item(
            'order:'.$order[0], 'order', 'fa-bag-shopping', 'New order '.$order[1], 'A buyer placed an order. Confirm and prepare it.',
            route('seller.orders'), 'View orders', $order[2],
        )));

        return $items;
    }

    /** Logistics partners: decisions about their account and rider applications waiting for their review. */
    private function logistics(User $owner): Collection
    {
        $items = $this->accountDecisions($owner, route('logistics.account'));
        $providerId = Schema::hasTable('logistics_providers') ? $owner->logisticsProvider?->id : null;

        if ($providerId && Schema::hasTable('riders')) {
            Rider::with('user')->where('logistics_provider_id', $providerId)->where('status', 'pending')->latest()->limit(self::LIMIT)->get()
                ->each(fn (Rider $rider) => $items->push($this->item(
                    'rider-application:'.$rider->id, 'account', 'fa-motorcycle', 'New rider application',
                    ($rider->user?->name ?? 'A rider').' applied to deliver for you.', route('logistics.riders', ['status' => 'pending']), 'Review rider', $rider->created_at,
                )));
        }

        return $items;
    }

    /** What admins decided in the last 30 days. An admin's own decisions start out read; other admins' are new. */
    private function decisionHistory(User $viewer): Collection
    {
        if (! Schema::hasTable('activity_logs')) {
            return collect();
        }

        return ActivityLog::whereIn('event', self::DECISION_EVENTS)->where('created_at', '>=', now()->subDays(30))
            ->latest('created_at')->latest('id')->limit(self::LIMIT)->get()
            ->map(function (ActivityLog $log) use ($viewer) {
                [$title, $icon] = $this->decisionLabel($log);
                $mine = (int) $log->user_id === (int) $viewer->id;
                $result = $log->properties['result'] ?? ($log->description.($log->subject_label ? ': '.$log->subject_label : '.'));
                $reason = $log->properties['reason'] ?? null;

                return $this->item(
                    'decision:'.$log->id, 'decision', $icon, $title,
                    ($mine ? 'You' : ($log->actor_name ?? 'An admin')).' · '.$result.($reason ? ' Reason: '.$reason : ''),
                    $this->decisionUrl($log), 'View', $log->created_at, seen: $mine,
                );
            });
    }

    /** Approvals, rejections, suspensions and reactivations of this account, as the owner sees them. */
    private function accountDecisions(User $user, string $url): Collection
    {
        if (! Schema::hasTable('activity_logs')) {
            return collect();
        }

        return ActivityLog::where('event', 'admin.users.status')->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)
            ->where('created_at', '>=', now()->subDays(60))->latest('created_at')->latest('id')->limit(10)->get()
            ->map(function (ActivityLog $log) use ($url) {
                $reason = $log->properties['reason'] ?? null;
                [$title, $icon, $message] = match ($log->properties['status'] ?? null) {
                    'active' => $this->reactivated($log)
                        ? ['Your account is active again', 'fa-rotate-left', 'ZAYLO reactivated your account. Welcome back.']
                        : ['Your account was approved', 'fa-circle-check', 'ZAYLO approved your application. You can now use every feature.'],
                    'rejected' => ['Your application was not approved', 'fa-circle-xmark', 'ZAYLO did not approve your application.'],
                    'suspended' => ['Your account was suspended', 'fa-ban', 'ZAYLO suspended your account.'],
                    'deactivated' => ['Your account was deactivated', 'fa-power-off', 'ZAYLO deactivated your account.'],
                    default => ['Your account status changed', 'fa-user-gear', 'ZAYLO updated your account status.'],
                };

                return $this->item('account-decision:'.$log->id, 'account', $icon, $title, $message.($reason ? ' Reason: '.$reason : ''), $url, 'View account', $log->created_at);
            });
    }

    /** @return array{0: string, 1: string} title and icon */
    private function decisionLabel(ActivityLog $log): array
    {
        $decision = $log->properties['decision'] ?? null;

        return match ($log->event) {
            'admin.users.status' => match ($log->properties['status'] ?? null) {
                'active' => $this->reactivated($log) ? ['Account reactivated', 'fa-rotate-left'] : ['Account approved', 'fa-user-check'],
                'rejected' => ['Application rejected', 'fa-user-xmark'],
                'suspended' => ['Account suspended', 'fa-ban'],
                'deactivated' => ['Account deactivated', 'fa-power-off'],
                default => ['Account status changed', 'fa-user-gear'],
            },
            'admin.identity.review' => $decision === 'approve' ? ['ID approved', 'fa-id-card'] : ['ID rejected', 'fa-id-card'],
            'admin.compliance.review' => $decision === 'approve' ? ['Listing approved', 'fa-circle-check'] : ['Listing rejected', 'fa-circle-xmark'],
            'admin.compliance.warn' => ['Seller warned', 'fa-triangle-exclamation'],
            default => ['Product visibility changed', 'fa-eye-slash'],
        };
    }

    /** "Active" after a suspension reads "is active again" in the saved result (AdminController::updateUserStatus). */
    private function reactivated(ActivityLog $log): bool
    {
        return str_contains((string) ($log->properties['result'] ?? ''), 'active again');
    }

    private function decisionUrl(ActivityLog $log): string
    {
        return match ($log->event) {
            'admin.users.status' => $log->subject_id && User::whereKey($log->subject_id)->exists() ? route('admin.users.show', $log->subject_id) : route('admin.users'),
            'admin.identity.review' => route('admin.identity'),
            default => route('admin.compliance'),
        };
    }

    private function item(string $key, string $type, string $icon, string $title, string $message, string $url, string $action, $at, bool $urgent = false, bool $seen = false): array
    {
        return compact('key', 'type', 'icon', 'title', 'message', 'url', 'action', 'urgent', 'seen') + ['at' => $at ?? now()];
    }

    private function withReadState(User $user, Collection $items): Collection
    {
        $items = $items->sortByDesc(fn (array $item) => $item['at']->getTimestamp())->values();
        $read = $items->isNotEmpty() && Schema::hasTable('buyer_notification_reads')
            ? array_fill_keys(DB::table('buyer_notification_reads')->where('user_id', $user->id)->whereIn('notification_key', $items->pluck('key'))->pluck('notification_key')->all(), true)
            : [];

        return $items->map(fn (array $item) => $item + ['read' => $item['seen'] || isset($read[$item['key']])]);
    }

    /** Seller and logistics partner applications, as on the Approvals page. */
    private function pendingApplications(): Builder
    {
        $roles = ['seller', 'logistics'];

        return User::where('status', 'pending')->where(fn (Builder $query) => Schema::hasColumn('users', 'role')
            ? $query->whereIn('role', $roles)
            : $query->whereHas('roles', fn (Builder $role) => $role->whereIn('name', $roles)));
    }
}
