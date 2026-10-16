<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Schema;

/**
 * The buyer, seller and courier (rider) user accounts behind an order, on either database layout.
 */
class OrderParties
{
    /** @return array{buyer: ?int, seller: ?int, courier: ?int} */
    public static function for(?Order $order): array
    {
        if (! $order) {
            return ['buyer' => null, 'seller' => null, 'courier' => null];
        }

        if (Order::usesLegacySchema()) {
            return [
                'buyer' => $order->user_id ? (int) $order->user_id : null,
                'seller' => $order->seller_id ? (int) $order->seller_id : null,
                'courier' => $order->shipment?->courier_id ? (int) $order->shipment->courier_id : null,
            ];
        }

        $sellerOrder = $order->sellerOrders()->with(['seller', 'shipment.rider'])->first();
        $rider = Schema::hasColumn('shipments', 'rider_id') ? $sellerOrder?->shipment?->rider : null;

        return [
            'buyer' => $order->buyer_id ? (int) $order->buyer_id : null,
            'seller' => $sellerOrder?->seller?->user_id ? (int) $sellerOrder->seller->user_id : null,
            'courier' => $rider?->user_id ? (int) $rider->user_id : null,
        ];
    }
}
