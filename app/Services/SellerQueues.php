<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * What waits on a seller: orders to prepare and returns to handle, for the sidebar badges, and a signature of the
 * seller's orders, parcels and returns that changes whenever any of them does, so open pages can refresh themselves.
 */
class SellerQueues
{
    private const TO_PREPARE = ['placed', 'confirmed', 'processing'];

    private const TO_PREPARE_NORMALIZED = ['pending', 'accepted', 'packed'];

    public function ordersToPrepare(User $seller): int
    {
        $shopId = Shop::idForSeller($seller);
        if (! $shopId) {
            return 0;
        }

        return Order::usesLegacySchema()
            ? Order::where('seller_id', $shopId)->whereIn('status', self::TO_PREPARE)->count()
            : SellerOrder::where('seller_id', $shopId)->whereIn('status', self::TO_PREPARE_NORMALIZED)->count();
    }

    /** Complaints and returns about this seller's orders. */
    public function returns(User $seller): Builder
    {
        if (! Schema::hasTable('disputes')) {
            return Dispute::query()->whereRaw('1 = 0');
        }
        $shopId = Shop::idForSeller($seller) ?? 0;

        return Dispute::query()->whereHas('order', function (Builder $order) use ($seller, $shopId) {
            Order::usesLegacySchema()
                ? $order->where('seller_id', $shopId)
                : $order->whereHas('sellerOrders.seller', fn (Builder $shop) => $shop->where('user_id', $seller->id));
        });
    }

    /** New cases, returned items to confirm, and refunds still to issue. */
    public function returnsNeedingAction(User $seller): int
    {
        return $this->returns($seller)->where('status', '!=', 'rejected')->get()
            ->filter(fn (Dispute $dispute) => $dispute->status === 'open'
                || (Dispute::tracksRefunds() && ($dispute->canConfirmReceipt() || $dispute->canRefund())))
            ->count();
    }

    /** Changes when an order, parcel or return of this seller is added or updated. */
    public function signature(User $seller): string
    {
        $shopId = Shop::idForSeller($seller) ?? 0;
        $legacy = Order::usesLegacySchema();
        $orders = $legacy ? Order::where('seller_id', $shopId) : SellerOrder::where('seller_id', $shopId);
        $parcels = Shipment::query()->when($legacy,
            fn (Builder $query) => $query->whereIn('order_id', Order::where('seller_id', $shopId)->select('id')),
            fn (Builder $query) => $query->whereIn('seller_order_id', SellerOrder::where('seller_id', $shopId)->select('id')),
        );
        $returns = $this->returns($seller);

        return sha1(implode('|', [
            (clone $orders)->count(), (clone $orders)->max('updated_at'),
            (clone $parcels)->count(), (clone $parcels)->max('updated_at'),
            (clone $returns)->count(), (clone $returns)->max('updated_at'),
        ]));
    }
}
