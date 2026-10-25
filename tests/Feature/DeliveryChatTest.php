<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class DeliveryChatTest extends TestCase
{
    use SignsInMobile;
    use LegacyOrderTables;
    use RefreshDatabase;

    private User $buyer;

    private User $rider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->rider = $this->approvedRider(['name' => 'Jun Reyes']);
    }

    /** Returns [orderId, shipmentId]. */
    private function delivery(string $status = 'assigned', bool $assigned = true): array
    {
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-CHAT-1', 'user_id' => $this->buyer->id, 'status' => 'shipped', 'subtotal' => 500,
            'shipping_fee' => 49, 'total' => 549, 'payment_method' => 'cod', 'recipient_name' => 'Maria Santos',
            'phone' => '09170000000', 'shipping_address' => '12 Rizal St, Calamba, Laguna', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $shipmentId = DB::table('shipments')->insertGetId([
            'order_id' => $orderId, 'courier_id' => $assigned ? $this->rider->id : null, 'tracking_number' => 'TRK-CHAT',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$orderId, $shipmentId];
    }

    private function asRider(): void
    {
        $this->signInMobile($this->rider);
    }

    private function asBuyer(?User $buyer = null): void
    {
        $this->signInMobile($buyer ?? $this->buyer);
    }

    public function test_rider_and_buyer_exchange_messages_and_unread_counts_clear_when_read(): void
    {
        [$orderId, $shipmentId] = $this->delivery('out_for_delivery');

        $this->asRider();
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/chat", ['body' => 'Hi! I am on my way.'])
            ->assertCreated()
            ->assertJsonPath('message.mine', true);

        $this->asBuyer();
        $this->getJson("/api/mobile/orders/$orderId")
            ->assertJsonPath('order.rider_chat.rider_name', 'Jun')
            ->assertJsonPath('order.rider_chat.open', true)
            ->assertJsonPath('order.rider_chat.unread', 1);
        $this->getJson("/api/mobile/orders/$orderId/chat")
            ->assertOk()
            ->assertJsonPath('chat.other_name', 'Jun')
            ->assertJsonPath('chat.other_role', 'Rider')
            ->assertJsonPath('chat.messages.0.body', 'Hi! I am on my way.')
            ->assertJsonPath('chat.messages.0.mine', false);
        $this->getJson("/api/mobile/orders/$orderId")->assertJsonPath('order.rider_chat.unread', 0);
        $this->postJson("/api/mobile/orders/$orderId/chat", ['body' => 'Please leave it at the gate.'])->assertCreated();

        $this->asRider();
        $this->getJson("/api/mobile/rider/deliveries/$shipmentId/chat")
            ->assertOk()
            ->assertJsonPath('chat.other_name', 'Maria')
            ->assertJsonPath('chat.open', true)
            ->assertJsonCount(2, 'chat.messages')
            ->assertJsonPath('chat.messages.0.read', true)
            ->assertJsonPath('chat.messages.1.mine', false);
    }

    public function test_retried_send_with_the_same_client_id_is_stored_once(): void
    {
        [, $shipmentId] = $this->delivery();
        $this->asRider();
        $payload = ['body' => 'Arriving in 5 minutes', 'client_id' => '6f1c1d2e-3b4a-4c5d-8e9f-0a1b2c3d4e5f'];

        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/chat", $payload)->assertCreated();
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/chat", $payload)->assertCreated();

        $this->assertSame(1, DB::table('delivery_messages')->count());
    }

    public function test_chat_is_read_only_after_delivery(): void
    {
        [$orderId, $shipmentId] = $this->delivery('out_for_delivery');
        $this->asRider();
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/chat", ['body' => 'Outside now'])->assertCreated();
        DB::table('shipments')->where('id', $shipmentId)->update(['status' => 'delivered']);

        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/chat", ['body' => 'Thanks!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'delivered']);

        $this->asBuyer();
        $this->getJson("/api/mobile/orders/$orderId/chat")
            ->assertOk()
            ->assertJsonPath('chat.open', false)
            ->assertJsonCount(1, 'chat.messages');
    }

    public function test_only_the_assigned_rider_and_the_ordering_buyer_can_use_the_chat(): void
    {
        [$orderId, $shipmentId] = $this->delivery();

        $this->signInMobile($this->approvedRider());
        $this->getJson("/api/mobile/rider/deliveries/$shipmentId/chat")->assertNotFound();
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/chat", ['body' => 'Hello'])->assertNotFound();

        $this->asBuyer(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $this->getJson("/api/mobile/orders/$orderId/chat")->assertNotFound();

        // Buyers cannot message a rider who has not been assigned yet.
        [$waitingOrderId] = $this->delivery('in_transit', assigned: false);
        $this->asBuyer();
        $this->getJson("/api/mobile/orders/$waitingOrderId/chat")->assertNotFound();
        $this->getJson("/api/mobile/orders/$waitingOrderId")->assertJsonPath('order.rider_chat', null);
    }
}
