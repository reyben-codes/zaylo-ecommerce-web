<?php

namespace App\Services;

use App\Models\DeliveryEvent;
use App\Models\IdentityVerification;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuyerNotificationFeed
{
    public function forUser(User $buyer): Collection
    {
        $now = now();
        $cutoff = $now->copy()->subDays(30);
        $notifications = collect();

        $products = Product::query()
            ->visibleToBuyers()
            ->where('created_at', '>=', $cutoff);

        Product::usesLegacySchema()
            ? $products->where('stock', '>', 0)
            : $products->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0));

        $products->latest()
            ->get(['id', 'name', 'slug', 'created_at'])
            ->each(function (Product $product) use ($notifications) {
                $notifications->push([
                    'key' => 'product:'.$product->id,
                    'type' => 'product',
                    'title' => 'New product arrived',
                    'message' => $product->name.' is now available. Take a look while it is in stock.',
                    'url' => route('products.show', $product),
                    'action' => 'View product',
                    'at' => $product->created_at,
                ]);
            });

        Voucher::query()
            ->where('type', 'free_shipping')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->get()
            ->each(function (Voucher $voucher) use ($notifications, $now, $cutoff) {
                $availableAt = $voucher->starts_at && $voucher->starts_at->gt($voucher->created_at)
                    ? $voucher->starts_at : $voucher->created_at;
                $expiresSoon = $voucher->expires_at && $voucher->expires_at->lte($now->copy()->addDays(7));

                if (! $expiresSoon && $availableAt->lt($cutoff)) {
                    return;
                }

                $at = $expiresSoon
                    ? $availableAt->max($voucher->expires_at->copy()->subDays(7))
                    : $availableAt;

                $notifications->push([
                    'key' => 'voucher:'.$voucher->id.($expiresSoon ? ':expiring' : ':available'),
                    'type' => 'voucher',
                    'title' => $expiresSoon ? 'Free shipping voucher expires soon' : 'Free shipping voucher available',
                    'message' => $expiresSoon
                        ? 'Use code '.$voucher->code.' at checkout by '.$voucher->expires_at->copy()->timezone('Asia/Manila')->format('M j, g:i A').' PHT.'
                        : 'Use code '.$voucher->code.' at checkout for free shipping.',
                    'url' => route('buyer.cart'),
                    'action' => 'View cart',
                    'at' => $at,
                ]);
            });

        $statusMessages = [
            'placed' => 'Your order was placed and is waiting for the seller.',
            'confirmed' => 'The seller confirmed your order.',
            'processing' => 'The seller is preparing your order.',
            'ready_for_pickup' => 'Your order is packed and ready to ship.',
            'assigned' => 'A courier has been assigned to your order.',
            'picked_up' => 'The seller shipped your order.',
            'in_transit' => 'Your order is on its way.',
            'out_for_delivery' => 'Your order is out for delivery.',
            'completed' => 'Your order is complete.',
            'delivered' => 'Your order was delivered. Confirm you received it.',
            'failed' => 'The rider could not deliver your order.',
            'cancelled' => 'Your order was cancelled.',
        ];
        // A failed attempt carries the rider's reason in its note ("Delivery attempt failed: …").
        $messageFor = fn (string $status, ?string $note) => $status === 'failed' && $note
            ? $note
            : ($statusMessages[$status] ?? 'Your order status was updated.');

        if (Schema::hasTable('delivery_events')) {
            DeliveryEvent::query()
                ->whereHas('shipment.sellerOrder.order', fn ($query) => $query->where('buyer_id', $buyer->id))
                ->with('shipment.sellerOrder.order')
                ->where('occurred_at', '>=', $cutoff)
                ->latest('occurred_at')
                ->get()
                ->each(function (DeliveryEvent $history) use ($notifications, $messageFor) {
                    $order = $history->shipment->sellerOrder->order;
                    $notifications->push([
                        'key' => 'order:'.$history->id,
                        'type' => 'order',
                        'title' => 'Order '.$order->reference.' update',
                        'message' => $messageFor($history->status, $history->note),
                        'url' => route('buyer.orders'),
                        'action' => 'View orders',
                        'at' => $history->occurred_at,
                    ]);
                });
        } elseif (Schema::hasTable('order_status_histories')) {
            OrderStatusHistory::query()
                ->whereHas('order', fn ($query) => $query->where('user_id', $buyer->id))
                ->with('order')
                ->where('created_at', '>=', $cutoff)
                ->latest()
                ->get()
                ->each(function (OrderStatusHistory $history) use ($notifications, $messageFor) {
                    $notifications->push([
                        'key' => 'order:'.$history->id,
                        'type' => 'order',
                        'title' => 'Order '.$history->order->order_number.' update',
                        'message' => $messageFor($history->status, $history->note),
                        'url' => route('buyer.orders'),
                        'action' => 'View orders',
                        'at' => $history->created_at,
                    ]);
                });
        }

        // The admin's decision on an uploaded ID (the ID form is on the account page).
        if (Schema::hasTable('identity_verifications')) {
            IdentityVerification::where('user_id', $buyer->id)->whereIn('status', ['approved', 'rejected'])
                ->whereNotNull('reviewed_at')->where('reviewed_at', '>=', $cutoff)->latest('reviewed_at')->get()
                ->each(function (IdentityVerification $check) use ($notifications) {
                    $approved = $check->status === 'approved';
                    $notifications->push([
                        'key' => 'identity:'.$check->id.':'.$check->status,
                        'type' => 'account',
                        'title' => $approved ? 'Your ID was approved' : 'Your ID was not approved',
                        'message' => $approved
                            ? 'Your account is now verified.'
                            : 'Please upload a clearer photo of a valid ID.'.($check->rejection_reason ? ' Reason: '.$check->rejection_reason : ''),
                        'url' => route('buyer.account'),
                        'action' => $approved ? 'View account' : 'Upload again',
                        'at' => $check->reviewed_at,
                    ]);
                });
        }

        $notifications = $notifications->sortByDesc(fn ($item) => $item['at']->getTimestamp())->values();
        if ($notifications->isEmpty()) {
            return $notifications;
        }

        $readKeys = DB::table('buyer_notification_reads')
            ->where('user_id', $buyer->id)
            ->pluck('notification_key');
        $read = array_fill_keys($readKeys->all(), true);

        return $notifications->map(function ($item) use ($read) {
            $item['read'] = isset($read[$item['key']]);

            return $item;
        });
    }
}
