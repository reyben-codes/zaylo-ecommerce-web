<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Where an order's money goes, for cash on delivery and for GCash paid straight to the seller.
 *
 * For one seller's order:
 *   S  = items subtotal              V  = voucher discount the shop joined (the shop pays it)
 *   F  = full shipping fee           Fb = shipping the buyer paid (F less ZAYLO's free-shipping voucher)
 *   c  = ZAYLO's commission rate (MarketplaceLedger::rate())
 *
 *   The buyer pays            T = S + Fb − V
 *   The logistics partner     earns 80% of F (LogisticsPayouts), of which the rider's commission is RiderWallet::COMMISSION
 *   The seller keeps          S − V − c·S
 *   ZAYLO keeps               c·S + Fb − 0.8·F  (its 20% of shipping, less any free shipping it gave)
 *
 * Cash on delivery: the rider collects T, hands it to the hub at time-out (CashHandovers), the partner keeps its share and
 * ZAYLO owes the seller S − V − c·S once the order is complete.
 * GCash: the buyer sent T to the seller, so the seller owes ZAYLO c·S + Fb; it is taken from the seller's next COD payout,
 * or shown as due when there is none.
 */
class Settlement
{
    /** Orders that count once the buyer has them. */
    public const COMPLETE = ['delivered', 'completed'];

    /** @return array<string, float|string|bool> */
    public function forOrder(Order $order): array
    {
        $items = round((float) $order->subtotal, 2);
        $paidShipping = round((float) $order->shipping_fee, 2);
        $zayloShipping = round((float) ($order->shipping_discount ?? 0), 2);
        $fullShipping = $paidShipping + $zayloShipping;
        $voucher = Schema::hasColumn('orders', 'voucher_discount') ? round((float) $order->voucher_discount, 2) : 0.0;
        $commission = round($items * MarketplaceLedger::rate(), 2);
        $partner = round($fullShipping * LogisticsPayouts::partnerPercent() / 100, 2);
        $buyerPaid = round($items + $paidShipping - $voucher, 2);
        $sellerKeeps = round($items - $voucher - $commission, 2);
        $gcash = $order->payment_method === 'gcash';
        $complete = in_array($order->status, self::COMPLETE, true);

        return [
            'method' => $gcash ? 'gcash' : 'cod',
            'complete' => $complete,
            'items' => $items,
            'shipping' => $fullShipping,
            'shipping_paid_by_buyer' => $paidShipping,
            'free_shipping_by_zaylo' => $zayloShipping,
            'voucher_by_seller' => $voucher,
            'buyer_paid' => $buyerPaid,
            'commission' => $commission,
            'partner_share' => $partner,
            'seller_keeps' => $sellerKeeps,
            'zaylo_keeps' => round($commission + $paidShipping - $partner, 2),
            // Who holds the buyer's money, and what moves once the order is complete.
            'collected_by' => $gcash ? 'seller' : 'rider',
            'zaylo_owes_seller' => $complete && ! $gcash ? $sellerKeeps : 0.0,
            'seller_owes_zaylo' => $complete && $gcash ? round($commission + $paidShipping, 2) : 0.0,
        ];
    }

    /**
     * A seller's settlement over their completed orders: what ZAYLO owes for COD orders, less what the seller owes for
     * GCash orders. Positive: ZAYLO pays the seller; negative: the seller pays ZAYLO.
     *
     * @return array{orders: Collection, owed_to_seller: float, owed_by_seller: float, balance: float}
     */
    public function forSeller(User $seller, int $limit = 100): array
    {
        $shopId = Shop::idForSeller($seller);
        $orders = $shopId && Order::usesLegacySchema()
            ? Order::where('seller_id', $shopId)->whereIn('status', self::COMPLETE)->latest('placed_at')->limit($limit)->get()
            : collect();
        $rows = $orders->map(fn (Order $order) => ['order' => $order] + $this->forOrder($order));
        $toSeller = round($rows->sum('zaylo_owes_seller'), 2);
        $bySeller = round($rows->sum('seller_owes_zaylo'), 2);

        return ['orders' => $rows, 'owed_to_seller' => $toSeller, 'owed_by_seller' => $bySeller, 'balance' => round($toSeller - $bySeller, 2)];
    }
}
