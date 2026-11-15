<?php

namespace Tests\Unit;

use App\Support\OrderNotificationText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderNotificationTextTest extends TestCase
{
    public static function events(): array
    {
        return [
            'checkout' => ['placed', 'Order placed by buyer.', 'Order placed', 'waiting for the seller'],
            'seller confirms' => ['confirmed', 'Updated by seller.', 'Order confirmed', 'seller confirmed'],
            'shipping' => ['picked_up', null, 'Order shipped', 'seller shipped'],
            'delivery' => ['out_for_delivery', null, 'Order out for delivery', 'rider is on the way'],
            'delivered' => ['delivered', null, 'Order delivered', 'Confirm you received it'],
            'cancelled' => ['cancelled', null, 'Order cancelled', 'was cancelled'],
            'returned' => ['returned', null, 'Parcel returned to seller', 'returned to the seller'],
            'payment received, status unchanged' => ['placed', 'Seller received the GCash payment (ref. 123456).', 'GCash payment confirmed', 'confirmed receiving'],
            'payment rejected, status unchanged' => ['placed', 'Seller could not find GCash ref. 123456.', 'GCash payment not verified', 'could not find'],
            'pickup confirmed, status unchanged' => ['ready_for_pickup', 'Pickup confirmed by logistics for Nov 16, 2026.', 'Pickup confirmed', 'confirmed pickup'],
            'sorted, status unchanged' => ['in_transit', 'Parcel sorted (logistics).', 'Parcel sorted', 'sorted at the hub'],
            'assigned, status unchanged' => ['in_transit', 'Parcel rider assigned (logistics).', 'Rider assigned', 'assigned to deliver'],
            'failed delivery reason' => ['failed', 'Delivery attempt failed: Recipient unavailable.', 'Delivery attempt failed', 'Recipient unavailable'],
        ];
    }

    #[DataProvider('events')]
    public function test_describes_the_actual_event(string $status, ?string $note, string $title, string $detail): void
    {
        $text = OrderNotificationText::forStatus($status, $note);
        $this->assertSame($title, $text['title']);
        $this->assertStringContainsString($detail, $text['message']);
    }

    public function test_does_not_expose_payment_reference_or_unrelated_internal_notes(): void
    {
        $payment = OrderNotificationText::forStatus('placed', 'Seller received the GCash payment (ref. SECRET).');
        $this->assertStringNotContainsString('SECRET', $payment['message']);
        $placed = OrderNotificationText::forStatus('placed', 'Internal system note');
        $this->assertStringNotContainsString('Internal', $placed['message']);
    }

    public function test_unrecognized_status_still_explains_what_changed(): void
    {
        $this->assertSame('Your order status is now awaiting dispatch.',
            OrderNotificationText::forStatus('awaiting_dispatch')['message']);
    }
}
