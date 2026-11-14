<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\GcashPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\LegacyOrderTables;
use Tests\TestCase;

class PaymentCancellationSafetyTest extends TestCase
{
    use RefreshDatabase, LegacyOrderTables;

    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
        (require database_path('migrations/2026_11_07_000007_add_gcash_payment_checks.php'))->up();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
    }

    private function order(string $paymentStatus = GcashPayments::AWAITING): Order
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Reserved item', 'category' => 'storage_bins', 'price' => 1000, 'stock' => 4, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'SAFETY-'.Str::random(8), 'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->sellerShop()->id, 'status' => 'placed',
            'subtotal' => 1000, 'shipping_fee' => 120, 'total' => 1120, 'payment_method' => 'gcash',
            'recipient_name' => 'Buyer', 'phone' => '09171234567', 'shipping_address' => 'Manila',
            'placed_at' => now()->subHours(25),
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->variants()->firstOrFail()->id,
            'product_name' => $product->name, 'unit_price' => 1000, 'quantity' => 1, 'line_total' => 1000]);
        $order->payment()->create(['provider' => 'gcash', 'status' => $paymentStatus, 'amount' => 1120,
            'paid_at' => $paymentStatus === GcashPayments::PAID ? now() : null]);

        return $order;
    }

    public function test_a_stale_expiry_candidate_cannot_cancel_a_payment_verified_since_it_was_selected(): void
    {
        $order = $this->order(GcashPayments::CHECKING);
        $candidate = clone $order;
        $candidate->load('payment');
        app(GcashPayments::class)->verify($order, $this->seller, true);

        $this->assertFalse(app(CheckoutService::class)->cancelUnpaid($candidate, 'Stale expiry request'));
        $this->assertSame('placed', $order->fresh()->status);
        $this->assertSame(GcashPayments::PAID, $order->payment()->value('status'));
        $this->assertEquals(4, $order->items->first()->product->fresh()->stock);
        $this->assertDatabaseMissing('order_status_histories', ['order_id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_unpaid_expiry_restores_stock_once_and_counts_only_cancelled_orders(): void
    {
        $unpaid = $this->order();
        $paid = $this->order(GcashPayments::PAID);
        $this->assertSame(1, app(GcashPayments::class)->expireDue(app(CheckoutService::class)));
        $this->assertSame('cancelled', $unpaid->fresh()->status);
        $this->assertEquals(5, $unpaid->items->first()->product->fresh()->stock);
        $this->assertSame('placed', $paid->fresh()->status);
        $this->assertFalse(app(CheckoutService::class)->cancelUnpaid($unpaid, 'Repeated expiry'));
        $this->assertEquals(5, $unpaid->items->first()->product->fresh()->stock);
    }

    public function test_buyer_and_seller_cannot_turn_a_received_payment_into_an_unpaid_cancellation(): void
    {
        $order = $this->order(GcashPayments::PAID);
        $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order), ['reason' => 'other', 'note' => 'Changed plans'])
            ->assertSessionHasErrors('order');
        $this->actingAs($this->seller)->patch(route('seller.orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('order');
        $this->assertSame('placed', $order->fresh()->status);
        $this->assertSame(GcashPayments::PAID, $order->payment()->value('status'));
        $this->assertEquals(4, $order->items->first()->product->fresh()->stock);
    }

    public function test_verification_rechecks_the_order_state_after_expiry(): void
    {
        $order = $this->order(GcashPayments::CHECKING);
        app(CheckoutService::class)->cancelUnpaid($order, 'Expired');
        // A stale verification page must not mark the cancelled order paid.
        $this->actingAs($this->seller)->patch(route('seller.orders.gcash', $order), ['decision' => 'received'])
            ->assertSessionHasErrors('gcash');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('cancelled', $order->payment()->value('status'));
    }

    public function test_a_recorded_receipt_is_protected_even_if_its_status_is_inconsistent(): void
    {
        $order = $this->order(GcashPayments::PAID);
        $order->payment()->update(['status' => GcashPayments::AWAITING]);
        $this->assertFalse(app(CheckoutService::class)->cancelUnpaid($order, 'Stale status'));
        $this->assertSame(0, app(GcashPayments::class)->expireDue(app(CheckoutService::class)));
        $this->assertSame('placed', $order->fresh()->status);
        $this->assertEquals(4, $order->items->first()->product->fresh()->stock);
        $this->assertNotNull($order->payment()->value('paid_at'));
    }
}
