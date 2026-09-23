<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_buyers_can_open_notifications(): void
    {
        $this->get(route('buyer.notifications'))->assertRedirect(route('login'));

        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('buyer.notifications'))->assertForbidden();
    }

    public function test_feed_shows_recent_real_updates_and_read_state_follows_the_buyer(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $otherBuyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create([
            'seller_id' => $seller->id, 'name' => 'Fresh arrival', 'category' => 'electronics',
            'price' => 500, 'stock' => 5, 'is_active' => true,
        ]);
        $voucher = Voucher::create([
            'code' => 'SOONSHIP', 'type' => 'free_shipping', 'expires_at' => now()->addDays(2),
        ]);
        $order = $this->makeOrder($buyer, 'ZAY-BUYER');
        $history = $order->statusHistory()->create(['status' => 'in_transit']);
        $otherOrder = $this->makeOrder($otherBuyer, 'ZAY-OTHER');
        $otherHistory = $otherOrder->statusHistory()->create(['status' => 'placed']);

        $this->actingAs($buyer)->get(route('buyer.notifications'))
            ->assertOk()
            ->assertSee('Fresh arrival')
            ->assertSee('SOONSHIP')
            ->assertSee('expires soon')
            ->assertSee('ZAY-BUYER')
            ->assertDontSee('ZAY-OTHER')
            ->assertSee('3 unread')
            ->assertSee('data-notification-count', false);

        $this->post(route('buyer.notifications.open'), ['key' => 'order:'.$otherHistory->id])->assertNotFound();
        $this->post(route('buyer.notifications.open'), ['key' => 'product:'.$product->id])
            ->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('buyer_notification_reads', [
            'user_id' => $buyer->id, 'notification_key' => 'product:'.$product->id,
        ]);
        $this->get(route('buyer.notifications'))->assertSee('2 unread');

        $this->post(route('buyer.notifications.read-all'))->assertRedirect(route('buyer.notifications'));
        $this->get(route('buyer.notifications'))->assertSee('0 unread')->assertDontSee('data-notification-count', false);
        $this->assertDatabaseHas('buyer_notification_reads', [
            'user_id' => $buyer->id, 'notification_key' => 'order:'.$history->id,
        ]);
        $this->assertDatabaseHas('buyer_notification_reads', [
            'user_id' => $buyer->id, 'notification_key' => 'voucher:'.$voucher->id.':expiring',
        ]);

        $this->actingAs($otherBuyer)->get(route('buyer.notifications'))
            ->assertOk()
            ->assertSee('ZAY-OTHER')
            ->assertDontSee('ZAY-BUYER')
            ->assertSee('3 unread');
    }

    public function test_unavailable_offers_and_products_do_not_create_notifications(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Product::create([
            'seller_id' => $seller->id, 'name' => 'Hidden product', 'category' => 'electronics',
            'price' => 500, 'stock' => 5, 'is_active' => false,
        ]);
        Voucher::create([
            'code' => 'EXPIRED', 'type' => 'free_shipping', 'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($buyer)->get(route('buyer.notifications'))
            ->assertOk()
            ->assertSee("You're all caught up", false)
            ->assertDontSee('Hidden product')
            ->assertDontSee('EXPIRED');
    }

    private function makeOrder(User $buyer, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'user_id' => $buyer->id,
            'status' => 'placed',
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
