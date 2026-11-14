<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\SellerOrder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SellerLogistics
{
    /** Called inside the seller's fulfilment transaction after locking the order. */
    public function assign(Order $order, LogisticsProvider $partner, ?SellerOrder $sellerOrder = null): void
    {
        $partner = LogisticsProvider::acceptingParcels()->whereKey($partner->id)->lockForUpdate()->first();
        if (! $partner) {
            throw ValidationException::withMessages(['logistics_provider_id' => 'Choose an approved, active ZAYLO logistics partner.']);
        }
        $record = $sellerOrder ?? $order;
        if (Schema::hasColumn($record->getTable(), 'logistics_provider_id')) {
            $record->update(['logistics_provider_id' => $partner->id]);
        }

        $snapshot = $sellerOrder?->settlement_breakdown ?? Settlement::snapshotFor($order);
        // New checkouts reserve the shipping charge; rider terms are agreed when the seller selects a partner.
        // Earlier orders keep their recorded financial policy and rate.
        if (($snapshot['logistics_selection'] ?? null) !== 'seller' || isset($snapshot['logistics_provider_id'])) {
            return;
        }
        $snapshot = Settlement::deliveryBreakdown(
            (float) $snapshot['product_price'], (float) $snapshot['shipping_paid_by_buyer'],
            (float) $snapshot['free_shipping_by_zaylo'], (float) $snapshot['free_shipping_by_seller'], $partner->riderRate(),
        ) + ['logistics_selection' => 'seller', 'logistics_provider_id' => $partner->id];
        if (Schema::hasColumn($record->getTable(), 'settlement_breakdown')) {
            $record->update(['settlement_breakdown' => $snapshot]);
        }
        if (! $sellerOrder && ($payment = $order->payment)) {
            $payment->update(['payload' => array_merge($payment->payload ?? [], ['commission_breakdown' => $snapshot])]);
        }
    }
}
