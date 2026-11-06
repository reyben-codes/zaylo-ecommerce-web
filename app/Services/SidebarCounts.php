<?php

namespace App\Services;

use App\Http\Controllers\Concerns\QueriesLogisticsParcels;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\IdentityVerification;
use App\Models\Message;
use App\Models\Product;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * The numbers on the Seller, Admin and Logistics Center sidebars: what is waiting in each section. Pages render
 * them, and seller-live-updates.js refreshes them every 20 seconds from the portal's updates endpoint.
 *
 * @phpstan-type Counts array<string, array{0:int, 1:string}> key => [total, what the total means]
 */
class SidebarCounts
{
    use QueriesLogisticsParcels;

    public function __construct(
        private readonly WorkspaceNotificationFeed $notifications,
        private readonly SellerQueues $seller,
        private readonly LogisticsMessenger $logisticsChat,
        private readonly ReturnPickups $returns,
    ) {}

    /** @return array<string, array{0:int, 1:string}> */
    public function for(?User $user, string $portal): array
    {
        if (! $user) {
            return [];
        }
        $counts = ['notifications' => [$this->notifications->unreadCount($user), 'unread']];

        return $counts + match ($portal) {
            'seller' => $this->seller($user),
            'admin' => $this->admin(),
            'logistics' => $this->logistics($user),
            default => [],
        };
    }

    /** Just the totals, for the JSON the sidebars poll. */
    public function totals(?User $user, string $portal): array
    {
        return array_map(fn (array $count) => $count[0], $this->for($user, $portal));
    }

    private function seller(User $seller): array
    {
        return [
            'orders' => [$this->seller->ordersToPrepare($seller), 'to prepare'],
            'returns' => [$this->seller->returnsNeedingAction($seller), 'to handle'],
            'products' => [$this->rejectedListings($seller), 'to fix'],
            'chat' => [$this->unreadBuyerChats($seller), 'unread'],
            'logistics_chat' => [$this->logisticsChat->unreadCount($seller), 'unread'],
        ];
    }

    private function admin(): array
    {
        return [
            'account_approvals' => [$this->pendingAccounts(), 'waiting'],
            'product_approvals' => [Product::usesReview() ? Product::where('review_status', Product::REVIEW_PENDING)->notArchived()->count() : 0, 'waiting'],
            'identity' => [Schema::hasTable('identity_verifications') ? IdentityVerification::where('status', 'pending')->count() : 0, 'to check'],
            'disputes' => [Schema::hasTable('disputes') ? Dispute::where('status', 'open')->count() : 0, 'new'],
            'chat' => [$this->unreadSupport(), 'unread'],
        ];
    }

    private function logistics(User $owner): array
    {
        $provider = $owner->logisticsProvider;
        if (! $provider) {
            return [];
        }
        $stage = fn (array $stages) => ShipmentWorkflow::whereStage($this->parcels(), $stages)->count();
        $awaitingRider = ShipmentWorkflow::whereStage($this->parcels(), [ShipmentWorkflow::assignableStage()])
            ->where(function (Builder $query) {
                foreach (['rider_id', 'courier_id'] as $column) {
                    if (Schema::hasColumn('shipments', $column)) {
                        $query->whereNull($column);
                    }
                }
            })->count();
        $activeReturns = Schema::hasTable('disputes')
            ? $this->returns->forProvider($provider)->reject(fn (Dispute $case) => $case->returnReceived() || $case->status === 'rejected')->count()
            : 0;

        return [
            'pickups' => [$stage(['ready']), 'to confirm'],
            'incoming' => [$stage(['picked_up']), 'on the way to the hub'],
            'sorting' => [ShipmentWorkflow::hasHubSteps() ? $stage(['in_transit']) : 0, 'to sort'],
            'assignments' => [$awaitingRider, 'waiting for a rider'],
            'returns' => [$activeReturns, 'in progress'],
            'riders' => [$provider->riders()->where('status', 'pending')->count(), 'to review'],
            'messages' => [$this->logisticsChat->unreadCount($owner), 'unread'],
        ];
    }

    /** Seller and logistics sign-ups waiting for an admin (riders are reviewed by their partner). */
    private function pendingAccounts(): int
    {
        $roles = ['seller', 'logistics'];

        return User::where('status', 'pending')
            ->when(
                Schema::hasColumn('users', 'role'),
                fn (Builder $query) => $query->whereIn('role', $roles),
                fn (Builder $query) => $query->whereHas('roles', fn (Builder $role) => $role->whereIn('name', $roles)),
            )->count();
    }

    /** Messages from users to ZAYLO support that no admin has opened. */
    private function unreadSupport(): int
    {
        if (! Schema::hasTable('support_messages')) {
            return 0;
        }

        return SupportMessage::whereNull('read_at')
            ->whereHas('thread', fn (Builder $thread) => $thread->whereColumn('support_threads.user_id', 'support_messages.sender_id'))
            ->count();
    }

    /** Listings an admin rejected that the seller still has to fix. */
    private function rejectedListings(User $seller): int
    {
        $shopId = Shop::idForSeller($seller);

        return $shopId && Product::usesReview()
            ? Product::where('seller_id', $shopId)->where('review_status', Product::REVIEW_REJECTED)->notArchived()->count()
            : 0;
    }

    /** Buyer messages in the seller's shop chats that the seller has not read. */
    private function unreadBuyerChats(User $seller): int
    {
        if (! Schema::hasTable('messages')) {
            return 0;
        }
        $userId = (int) $seller->id;

        return Message::whereNull('read_at')->where('sender_id', '!=', $userId)
            ->whereIn('conversation_id', Conversation::forUser($userId)->select('id'))->count();
    }
}
