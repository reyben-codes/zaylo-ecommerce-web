<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\GcashPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\LegacyOrderTables;
use Tests\Support\PsgcFixtures;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * GCash without the GCash API: the buyer pays the seller's own GCash and gives the reference number; the seller checks
 * it before confirming the order; unpaid GCash orders are cancelled after a day.
 */
class GcashPaymentTest extends TestCase
{
    use LegacyOrderTables, PsgcFixtures, RefreshDatabase, SignsInMobile;

    private User $seller;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Mara Cruz']);
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
    }

    public function test_checkout_shows_each_sellers_gcash_and_checks_the_reference_format(): void
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Rattan Lamp', 'category' => 'storage_bins', 'price' => 450, 'stock' => 5, 'is_active' => true]);
        $this->buyer->addresses()->create($this->savedAddressData());
        $this->actingAs($this->buyer)->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();

        // No GCash account yet: GCash cannot be chosen for this seller.
        $this->get(route('buyer.checkout.show'))->assertOk()
            ->assertSee('has not set up GCash yet')->assertDontSee('data-gcash-pay', false);

        // The seller adds it in Seller Center.
        $this->actingAs($this->seller)->patch(route('seller.account.gcash'), ['gcash_number' => '0917123', 'gcash_name' => 'Mara Cruz'])->assertSessionHasErrors('gcash_number');
        $this->patch(route('seller.account.gcash'), ['gcash_number' => '09171234567', 'gcash_name' => 'Mara Cruz'])->assertSessionHasNoErrors();
        $this->get(route('seller.account'))->assertSee('GCash for buyer payments')->assertSee('09171234567');

        $this->actingAs($this->buyer)->get(route('buyer.checkout.show'))->assertOk()
            ->assertSee('data-gcash-pay', false)->assertSee('GCash <b>09171234567</b> · Mara Cruz', false)
            ->assertSee('name="gcash_references['.$product->seller_id.']"', false)->assertSee('js/checkout-gcash.js', false);

        // A reference that is not 13 digits is refused before anything is ordered.
        $this->post(route('buyer.checkout'), [
            'address_id' => $this->buyer->addresses()->first()->id, 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'gcash',
            'gcash_references' => [$product->seller_id => '12345'],
        ])->assertSessionHasErrors('gcash_references.'.$product->seller_id);
        $this->assertSame(0, Order::count());
        $this->assertSame(['status' => GcashPayments::CHECKING, 'gcash_reference' => '1234567890123'], array_intersect_key(GcashPayments::checkoutPayment('1234567890123'), ['status' => 1, 'gcash_reference' => 1]));
        $this->assertSame(['status' => GcashPayments::AWAITING], GcashPayments::checkoutPayment(null));
    }

    public function test_the_seller_checks_the_reference_before_confirming_and_unpaid_orders_expire(): void
    {
        $this->useLegacyOrderTables();
        (require database_path('migrations/2026_11_07_000007_add_gcash_payment_checks.php'))->up();
        (require database_path('migrations/2026_11_07_000006_add_cancellation_details_to_orders.php'))->up();
        $this->seller->forceFill(['gcash_number' => '09171234567', 'gcash_name' => 'Mara Cruz'])->save();
        $order = $this->gcashOrder();

        // The buyer sees where to pay and enters the reference (spaces are fine; a wrong length is not).
        $this->actingAs($this->buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee('GCash · Waiting for your GCash reference')->assertSee('09171234567')->assertSee(route('buyer.orders.gcash', $order), false);
        $this->post(route('buyer.orders.gcash', $order), ['gcash_reference' => '123'])->assertSessionHasErrors(['gcash_reference' => 'Enter the 13-digit reference number from your GCash receipt.']);
        $this->post(route('buyer.orders.gcash', $order), ['gcash_reference' => '1234 567 890123'])->assertSessionHasNoErrors();
        $this->assertSame(['pending_verification', '1234567890123'], [$order->payment()->value('status'), $order->payment()->value('gcash_reference')]);

        // Confirm order waits until the seller found the payment.
        $this->actingAs($this->seller)->get(route('seller.orders'))->assertOk()
            ->assertSee('Reference <b>1234567890123</b>', false)->assertSee('Payment received')
            ->assertDontSee('Confirm order');
        $this->patch(route('seller.orders.status', $order), ['status' => 'confirmed'])
            ->assertSessionHasErrors(['status' => "Check the buyer's GCash payment first: open your GCash app, find the reference, then mark it received."]);

        // Not found: the buyer sees the seller's note and can send it again.
        $this->patch(route('seller.orders.gcash', $order), ['decision' => 'not_found'])->assertSessionHasErrors('note');
        $this->patch(route('seller.orders.gcash', $order), ['decision' => 'not_found', 'note' => 'No payment with this reference.'])->assertSessionHasNoErrors();
        $this->actingAs($this->buyer)->get(route('buyer.orders'))->assertSee('Seller could not find this payment')->assertSee('No payment with this reference.');
        $this->post(route('buyer.orders.gcash', $order), ['gcash_reference' => '9876543210987'])->assertSessionHasNoErrors();

        // Received: now the seller can confirm.
        $this->actingAs($this->seller)->patch(route('seller.orders.gcash', $order), ['decision' => 'received'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->payment()->value('status'));
        $this->patch(route('seller.orders.status', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'note' => 'Seller received the GCash payment (ref. 9876543210987).']);

        // The app shows the same state and accepts a reference.
        $unpaid = $this->gcashOrder();
        $this->signInMobile($this->buyer);
        $this->getJson("/api/mobile/orders/{$unpaid->id}")->assertOk()
            ->assertJsonPath('order.gcash.status', 'awaiting_reference')->assertJsonPath('order.gcash.can_submit', true)
            ->assertJsonPath('order.gcash.seller_account.number', '09171234567');
        $this->postJson("/api/mobile/orders/{$unpaid->id}/gcash-reference", ['gcash_reference' => '5555555555555'])->assertOk()
            ->assertJsonPath('gcash.status', 'pending_verification');

        // A GCash order still unpaid after 24 hours is cancelled when the lists open.
        $this->travel(GcashPayments::EXPIRE_HOURS + 1)->hours();
        $this->getJson('/api/mobile/orders')->assertOk();
        $this->assertSame('cancelled', $unpaid->fresh()->status);
        $this->assertSame('confirmed', $order->fresh()->status, 'Paid orders are never expired.');
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $unpaid->id, 'note' => 'No GCash payment was received within 24 hours, so the order was cancelled.']);
    }

    private function gcashOrder(): Order
    {
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-'.strtoupper(uniqid()), 'user_id' => $this->buyer->id, 'seller_id' => $this->seller->sellerShop()->id,
            'status' => 'placed', 'subtotal' => 450, 'shipping_fee' => 49, 'total' => 499, 'payment_method' => 'gcash',
            'recipient_name' => $this->buyer->name, 'phone' => '09171234567', 'shipping_address' => '12 Rizal St, Calamba, Laguna',
            'placed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert(['order_id' => $orderId, 'provider' => 'gcash', 'status' => GcashPayments::AWAITING, 'amount' => 499, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $orderId, 'product_name' => 'Rattan Lamp', 'unit_price' => 450, 'quantity' => 1, 'line_total' => 450, 'created_at' => now(), 'updated_at' => now()]);

        return Order::findOrFail($orderId);
    }
}
