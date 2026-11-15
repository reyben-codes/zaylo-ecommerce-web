<?php

namespace App\Support;

class OrderNotificationText
{
    /**
     * Describe the saved event, rather than the order's potentially newer status.
     * @return array{title: string, message: string}
     */
    public static function forStatus(string $status, ?string $note = null): array
    {
        $note = trim(preg_replace('/\s+/u', ' ', strip_tags($note ?? '')) ?? '');

        // Several events keep the order's status unchanged. Their history note identifies what happened.
        foreach ([
            'Seller received the GCash payment' => ['GCash payment confirmed', 'The seller confirmed receiving your GCash payment.'],
            'Seller could not find GCash ref.' => ['GCash payment not verified', 'The seller could not find your GCash payment. Open the order to review the payment details.'],
            'Pickup confirmed by logistics' => ['Pickup confirmed', 'The logistics partner confirmed pickup from the seller.'],
            'Pickup declined by logistics:' => ['Pickup declined', 'The logistics partner declined the pickup. The seller needs to arrange it again.'],
            'Parcel sorted (logistics).' => ['Parcel sorted', 'Your parcel has been sorted at the hub.'],
            'Parcel rider assigned (logistics).' => ['Rider assigned', 'A rider has been assigned to deliver your parcel.'],
            'Handover confirmed at hub' => ['Parcel received at hub', 'Your parcel was received and checked at the logistics hub.'],
        ] as $prefix => [$title, $message]) {
            if (str_starts_with($note, $prefix)) return compact('title', 'message');
        }

        [$title, $message] = match ($status) {
            'placed', 'pending' => ['Order placed', 'Your order was placed and is waiting for the seller.'],
            'confirmed', 'accepted' => ['Order confirmed', 'The seller confirmed your order.'],
            'processing', 'packed' => ['Order being prepared', 'The seller is preparing your order.'],
            'ready', 'ready_for_pickup', 'ready_to_ship' => ['Order ready to ship', 'Your order is packed and ready to ship.'],
            'assigned' => ['Courier assigned', 'A courier has been assigned to your order.'],
            'picked_up', 'shipped' => ['Order shipped', 'The seller shipped your order.'],
            'in_transit' => ['Order in transit', 'Your order is on its way.'],
            'sorted' => ['Parcel sorted', 'Your parcel has been sorted at the hub.'],
            'out_for_delivery' => ['Order out for delivery', 'Your rider is on the way to your delivery address.'],
            'delivered' => ['Order delivered', 'Your order was delivered. Confirm you received it.'],
            'completed' => ['Order completed', 'Your order is complete.'],
            'failed' => ['Delivery attempt failed', $note !== '' ? $note : 'The rider could not deliver your order.'],
            'returned' => ['Parcel returned to seller', 'Your parcel was returned to the seller.'],
            'cancelled' => ['Order cancelled', 'Your order was cancelled.'],
            default => ['Order status changed', 'Your order status is now '.str_replace('_', ' ', $status).'.'],
        };

        return compact('title', 'message');
    }
}
