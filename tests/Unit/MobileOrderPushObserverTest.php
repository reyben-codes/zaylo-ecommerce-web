<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Observers\MobilePushObserver;
use App\Services\MobilePushNotifications;
use Tests\TestCase;

class MobileOrderPushObserverTest extends TestCase
{
    public function test_push_contains_the_saved_event_and_order_reference_and_keeps_its_destination(): void
    {
        // The current order may have moved on before this history event is processed.
        $buyer = new User;
        $buyer->id = 12;
        $order = \Mockery::mock(Order::class)->makePartial();
        $order->shouldReceive('getAttribute')->with('buyer')->andReturn($buyer);
        $order->shouldReceive('getAttribute')->with('order_number')->andReturn('ZAY-123');
        $order->shouldReceive('getAttribute')->with('id')->andReturn(42);
        $history = new OrderStatusHistory(['status' => 'placed', 'note' => 'Order placed by buyer.']);
        $history->id = 7;
        $history->setRelation('order', $order);

        $this->mock(\App\Services\AccountUpdates::class)->shouldReceive('send')->once()->with(
            12, 'order-history-7', 'orders', 'Order placed',
            'Order ZAY-123: Your order was placed and is waiting for the seller.', 'order', 42,
        );

        (new MobilePushObserver)->created($history);
    }
}
