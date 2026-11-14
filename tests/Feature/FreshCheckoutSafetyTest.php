<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class FreshCheckoutSafetyTest extends TestCase
{
    use RefreshDatabase, PsgcFixtures, SignsInMobile;

    private function checkoutData(User $buyer): array
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'gcash_number' => '09171234567', 'gcash_name' => 'Test Seller']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Checkout product '.Str::random(6), 'category' => 'blenders', 'price' => 100, 'stock' => 5, 'is_active' => true]);
        $item = app(CartService::class)->add($buyer, $product, 1);

        return [
            'address_id' => $buyer->addresses()->create($this->savedAddressData())->id,
            'idempotency_key' => (string) Str::uuid(), 'cart_item_ids' => [$item->id],
            'payment_method' => 'cod',
        ];
    }

    public function test_fresh_migrations_support_web_checkout_and_buyer_cancellation(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $data = $this->checkoutData($buyer);
        $this->actingAs($buyer)->post(route('buyer.checkout'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $order = $buyer->orders()->sole();
        $this->assertSame(100.0, $order->subtotal);
        $this->assertSame(1, $order->items()->count());
        $this->assertEquals($order->total, $order->payment->amount);
        $this->assertSame('pending', $order->sellerOrders()->sole()->status);
        $this->get(route('buyer.orders'))->assertOk()->assertSee($order->order_number);
        app(CheckoutService::class)->cancel($order, $buyer);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $order->items()->first()->variant->stock);
    }

    public function test_mobile_checkout_replays_the_original_result_after_cart_removal(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $data = $this->checkoutData($buyer);
        $this->signInMobile($buyer);
        $this->postJson('/api/mobile/checkout', $data)->assertCreated()->assertJsonPath('orders.0.status', 'placed');
        $this->postJson('/api/mobile/checkout', $data)->assertOk()->assertJsonPath('already_placed', true)->assertJsonCount(1, 'orders');
        // A second request that already passed the controller check is resolved under the buyer lock too.
        $orders = app(CheckoutService::class)->place($buyer, $data['cart_item_ids'], $data, '');
        $this->assertCount(1, $orders);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, $orders->first()->items()->first()->variant->stock);
    }

    public function test_same_key_does_not_block_a_different_buyer(): void
    {
        $one = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $two = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $dataOne = $this->checkoutData($one);
        $dataTwo = $this->checkoutData($two);
        $dataTwo['idempotency_key'] = $dataOne['idempotency_key'];
        $checkout = app(CheckoutService::class);
        $checkout->place($one, $dataOne['cart_item_ids'], $dataOne, '');
        $this->assertFalse($checkout->alreadyPlaced($dataTwo['idempotency_key'], $two));
        $checkout->place($two, $dataTwo['cart_item_ids'], $dataTwo, '');
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_fresh_schema_gcash_payment_can_be_verified_but_not_expired_after_payment(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $data = $this->checkoutData($buyer);
        $data['payment_method'] = 'gcash';
        $checkout = app(CheckoutService::class);
        $order = $checkout->place($buyer, $data['cart_item_ids'], $data, '')->sole();
        $payments = app(\App\Services\GcashPayments::class);
        $payments->submit($order, $buyer, '1234567890123');
        $payments->verify($order, \App\Services\GcashPayments::sellerOfOrder($order), true);
        $this->assertFalse($checkout->cancelUnpaid($order, 'Expired'));
        $this->assertSame('paid', $order->payment()->sole()->status);
        $this->assertSame('placed', $order->fresh()->status);
        $this->assertSame(0, $payments->expireDue($checkout, buyerId: $buyer->id));
    }

    public function test_two_sellers_and_voucher_survive_fresh_checkout_and_seller_preparation(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $one = $this->checkoutData($buyer);
        $two = $this->checkoutData($buyer);
        $one['cart_item_ids'] = array_merge($one['cart_item_ids'], $two['cart_item_ids']);
        $voucher = \App\Models\Voucher::create(['code' => 'FRESHSHIP', 'type' => 'free_shipping', 'usage_limit' => 1]);
        $checkout = app(CheckoutService::class);
        $orders = $checkout->place($buyer, $one['cart_item_ids'], $one, $voucher->code);
        $this->assertCount(2, $orders);
        $this->assertEquals(200, $orders->sum('total'));
        $this->assertEquals(0, $orders->sum('shipping_fee'));
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertEquals($orders->pluck('id')->all(), $checkout->place($buyer, $one['cart_item_ids'], $one, $voucher->code)->pluck('id')->all());
        $order = $orders->first();
        $seller = \App\Services\GcashPayments::sellerOfOrder($order);
        $this->actingAs($seller)->get(route('seller.orders'))->assertOk()->assertSee($order->order_number);
        $this->get(route('seller.orders', ['status' => 'to_prepare']))->assertOk()->assertSee($order->order_number)->assertDontSee($orders->last()->order_number);
        $this->actingAs($seller)->patch(route('seller.orders.status', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $order->sellerOrders()->sole()->status);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->patch(route('seller.orders.status', $order), ['status' => 'processing'])->assertSessionHasNoErrors();
        $partnerOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = \App\Models\LogisticsProvider::create(['user_id' => $partnerOwner->id, 'name' => 'Fixture Logistics', 'slug' => 'fresh-partner', 'status' => 'approved']);
        $this->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup', 'logistics_provider_id' => $partner->id])->assertSessionHasNoErrors();
        $this->assertSame($partner->id, $order->fresh()->shipment->logistics_provider_id);
        $this->assertSame('ready_to_ship', $order->sellerOrders()->sole()->status);
    }
}
