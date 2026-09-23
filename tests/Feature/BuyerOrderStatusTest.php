<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_open_order_status_from_the_store_header(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $this->actingAs($buyer)->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Order status and tracking"', false)
            ->assertSee(route('buyer.orders'));

        $this->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee('No orders yet');
    }

    public function test_status_filters_show_only_the_buyers_orders_in_the_right_stage(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $otherBuyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $toShip = $this->makeOrder($buyer, 'assigned', 'ZAY-TO-SHIP');
        $shipped = $this->makeOrder($buyer, 'in_transit', 'ZAY-SHIPPED');
        $delivered = $this->makeOrder($buyer, 'completed', 'ZAY-DELIVERED');
        $cancelled = $this->makeOrder($buyer, 'cancelled', 'ZAY-CANCELLED');
        $other = $this->makeOrder($otherBuyer, 'placed', 'ZAY-OTHER-BUYER');

        $shipped->shipment()->create(['tracking_number' => 'TRACK-123', 'status' => 'in_transit']);
        $shipped->statusHistory()->create(['status' => 'in_transit', 'note' => 'Updated by courier.']);

        $this->actingAs($buyer)->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee($toShip->order_number)
            ->assertSee($shipped->order_number)
            ->assertSee($delivered->order_number)
            ->assertSee($cancelled->order_number)
            ->assertDontSee($other->order_number)
            ->assertSee('TRACK-123')
            ->assertSee('Status history');

        $this->get(route('buyer.orders', ['status' => 'to_ship']))
            ->assertOk()
            ->assertSee($toShip->order_number)
            ->assertDontSee($shipped->order_number)
            ->assertDontSee($other->order_number);

        $this->get(route('buyer.orders', ['status' => 'shipped']))
            ->assertOk()
            ->assertSee($shipped->order_number)
            ->assertDontSee($toShip->order_number);

        $this->get(route('buyer.orders', ['status' => 'delivered']))
            ->assertOk()
            ->assertSee($delivered->order_number)
            ->assertDontSee($cancelled->order_number);

        $this->get(route('buyer.orders', ['status' => 'cancelled']))
            ->assertOk()
            ->assertSee($cancelled->order_number)
            ->assertDontSee($delivered->order_number);
    }

    private function makeOrder(User $buyer, string $status, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'user_id' => $buyer->id,
            'status' => $status,
            'subtotal' => 250,
            'shipping_fee' => 50,
            'total' => 300,
            'payment_method' => 'cod',
            'recipient_name' => $buyer->name,
            'phone' => '09171234567',
            'shipping_address' => 'Test Street, Manila',
            'placed_at' => now(),
        ]);
    }
}
