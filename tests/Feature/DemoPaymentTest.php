<?php

namespace Tests\Feature;

use App\Models\EscrowPayment;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\User;
use App\Services\DemoPayments;
use App\Services\EscrowPayments;
use App\Services\OrderReceipts;
use App\Services\SellerWallet;
use App\Services\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class DemoPaymentTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    private function fixture(): array
    {
        Http::preventStrayRequests();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'School Partner', 'slug' => 'school-partner', 'status' => 'approved']);
        config(['escrow.demo_enabled' => true, 'escrow.live' => false, 'escrow.secret_key' => '',
            'escrow.webhook_secret' => '', 'escrow.admin_user_id' => $admin->id]);
        $order = Order::create(['buyer_id' => $buyer->id, 'status' => 'awaiting_payment', 'reference' => 'SCHOOL-001', 'total_minor' => 112000,
            'payment_method' => 'gcash', 'shipping_address' => ['city' => 'Calamba'], 'settlement_breakdown' => Settlement::deliveryBreakdown(1000, 120)]);
        $sellerOrder = $order->sellerOrders()->create(['seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 100000,
            'shipping_fee_minor' => 12000, 'commission_minor' => 10000, 'status' => 'pending', 'settlement_breakdown' => Settlement::deliveryBreakdown(1000, 120)]);
        $sellerOrder->shipment()->create(['logistics_provider_id' => $provider->id, 'tracking_code' => 'SCHOOL-TRACK', 'status' => 'unassigned']);
        $order->payment()->create(['method' => 'gcash', 'provider' => 'gcash', 'amount_minor' => 112000, 'status' => 'pending']);
        $escrow = EscrowPayments::reserve($order);
        return compact('buyer', 'seller', 'admin', 'order', 'sellerOrder', 'escrow');
    }

    public function test_owner_gets_a_signed_local_checkout_without_gateway_keys(): void
    {
        $f = $this->fixture();
        $this->assertTrue(EscrowPayments::configured());
        $response = $this->signInMobile($f['buyer'])->postJson('/api/mobile/orders/'.$f['order']->id.'/gateway')->assertOk();
        $url = $response->json('checkout_url');
        $this->assertStringContainsString('/payments/checkout/'.$f['escrow']->id, $url);
        $this->get($url)->assertOk()->assertSee('Confirm payment')->assertSee('PHP 1,120.00')
            ->assertDontSee('Test mode')->assertDontSee('DEMO')->assertDontSee('MPIN')->assertDontSee('OTP');
        Http::assertNothingSent();
    }

    public function test_authorization_uses_saved_amount_and_receipt_releases_once(): void
    {
        $f = $this->fixture();
        $url = app(DemoPayments::class)->checkoutUrl($f['escrow']);
        $action = $this->get($url)->viewData('authorizeUrl');
        $this->post($action, ['decision' => 'approve', 'amount_minor' => 1])->assertRedirect();
        $this->post($action, ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('held', $f['escrow']->fresh()->status);
        $this->assertSame('paid', $f['order']->payment->fresh()->status);
        $this->assertSame('placed', $f['order']->fresh()->status);
        $this->assertNotNull($f['order']->fresh()->placed_at);
        $this->assertEquals(112000, $f['escrow']->fresh()->amount_minor);
        $this->assertDatabaseCount('settlement_wallet_entries', 0);
        $this->assertDatabaseCount('order_status_histories', 1);
        $f['sellerOrder']->update(['status' => 'delivered', 'delivered_at' => now()]);
        app(OrderReceipts::class)->confirm($f['order']->fresh(), $f['buyer']);
        app(EscrowPayments::class)->release($f['order']->fresh());
        $this->assertDatabaseCount('settlement_wallet_entries', 3);
        $this->assertEquals(800, app(SellerWallet::class)->balance($f['seller']));
        Http::assertNothingSent();
    }

    public function test_declining_keeps_the_order_unpaid_and_allows_retry(): void
    {
        $f = $this->fixture();
        $url = app(DemoPayments::class)->checkoutUrl($f['escrow']);
        $action = $this->get($url)->viewData('authorizeUrl');
        $this->post($action, ['decision' => 'decline'])->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Payment not completed');
        $this->assertSame('pending', $f['escrow']->fresh()->status);
        $this->assertSame('pending', $f['order']->payment->fresh()->status);
        $this->assertSame('awaiting_payment', $f['order']->fresh()->status);
        $this->assertNull($f['order']->fresh()->placed_at);
        $this->assertSame(0, app(\App\Services\SellerQueues::class)->ordersToPrepare($f['seller']));
        $this->actingAs($f['seller'])->get(route('seller.orders'))->assertOk()->assertDontSee('SCHOOL-001');
        $this->assertFalse(\App\Services\GcashPayments::allowsConfirm($f['order']->fresh()));
        $payload = \App\Support\MobilePayload::order($f['order']->fresh());
        $this->assertSame('To pay', $payload['status_label']);
        $this->assertStringContainsString('cannot be prepared or shipped', $payload['status_message']);
        $this->actingAs($f['seller'])->patch(route('seller.orders.status', $f['order']), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');
        $this->assertSame('pending', $f['sellerOrder']->fresh()->status);
        $this->post($action, ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('held', $f['escrow']->fresh()->status);
        $this->assertSame(1, app(\App\Services\SellerQueues::class)->ordersToPrepare($f['seller']));
        $this->actingAs($f['seller'])->patch(route('seller.orders.status', $f['order']), ['status' => 'confirmed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('accepted', $f['sellerOrder']->fresh()->status);
    }

    public function test_unpaid_checkout_cannot_be_fulfilled_even_with_a_paid_receipt(): void
    {
        $f = $this->fixture();
        $f['order']->payment->update(['status' => 'paid']);
        $this->assertFalse(\App\Services\GcashPayments::allowsConfirm($f['order']->fresh()));
        $this->actingAs($f['seller'])->patch(route('seller.orders.status', $f['order']), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');
        $this->assertSame('pending', $f['sellerOrder']->fresh()->status);
        $this->assertSame('pending', $f['escrow']->fresh()->status);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\SellerLogistics::class)->assign($f['order']->fresh(), $f['sellerOrder']->shipment->provider, $f['sellerOrder']);
    }

    public function test_unsigned_expired_and_other_users_checkouts_are_rejected(): void
    {
        $f = $this->fixture();
        $this->get('/payments/checkout/'.$f['escrow']->id)->assertForbidden();
        $expired = URL::temporarySignedRoute('payment.checkout', now()->subMinute(), ['escrow' => $f['escrow']->id]);
        $this->get($expired)->assertForbidden();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($other)->postJson('/api/mobile/orders/'.$f['order']->id.'/gateway')->assertNotFound();
        $this->assertSame('pending', $f['escrow']->fresh()->status);
    }

    public function test_upgrade_converts_only_unpaid_gateway_orders_to_checkouts(): void
    {
        $f = $this->fixture();
        $f['order']->update(['status' => 'placed', 'placed_at' => now()]);
        $migration = require database_path('migrations/2026_11_16_000001_keep_unpaid_gateway_checkouts_unplaced.php');
        $migration->up();
        $this->assertSame('awaiting_payment', $f['order']->fresh()->status);
        $this->assertNull($f['order']->fresh()->placed_at);
        app(DemoPayments::class)->authorize($f['escrow'], 'approve');
        $placedAt = $f['order']->fresh()->placed_at;
        $migration->up();
        $this->assertSame('placed', $f['order']->fresh()->status);
        $this->assertEquals($placedAt, $f['order']->fresh()->placed_at);
    }

    public function test_disabling_demo_or_enabling_live_mode_blocks_local_authorization(): void
    {
        $f = $this->fixture();
        $url = app(DemoPayments::class)->checkoutUrl($f['escrow']);
        $action = $this->get($url)->viewData('authorizeUrl');
        config(['escrow.demo_enabled' => false]);
        $this->post($action, ['decision' => 'approve'])->assertNotFound();
        config(['escrow.demo_enabled' => true, 'escrow.live' => true]);
        $this->assertFalse(EscrowPayments::configured());
        $this->post($action, ['decision' => 'approve'])->assertNotFound();
        $this->assertSame('pending', $f['escrow']->fresh()->status);
    }

    public function test_local_authorization_cannot_pay_real_sessions_or_cancelled_orders(): void
    {
        $f = $this->fixture();
        $action = URL::temporarySignedRoute('payment.authorize', now()->addMinutes(10), ['escrow' => $f['escrow']->id]);
        $f['escrow']->update(['checkout_session' => 'cs_real', 'livemode' => true]);
        $this->post($action, ['decision' => 'approve'])->assertNotFound();
        $f['escrow']->update(['checkout_session' => 'demo_owned', 'livemode' => false]);
        $f['sellerOrder']->update(['status' => 'cancelled']);
        $this->post($action, ['decision' => 'approve'])->assertSessionHasErrors('payment');
        $this->assertSame('pending', $f['escrow']->fresh()->status);
    }

    public function test_android_browser_can_open_checkout_and_gateway_webhooks_ignore_local_sessions(): void
    {
        $f = $this->fixture();
        $url = app(DemoPayments::class)->checkoutUrl($f['escrow']);
        $this->withHeader('User-Agent', 'Android')->get($url)->assertOk();
        app(EscrowPayments::class)->capture(['data' => ['type' => 'checkout_session.payment.paid', 'livemode' => false,
            'data' => ['id' => $f['escrow']->checkout_session, 'attributes' => ['payments' => [['id' => 'pay_forged',
                'attributes' => ['status' => 'paid', 'amount' => 112000, 'currency' => 'PHP']]]]]]]);
        $this->assertSame('pending', $f['escrow']->fresh()->status);
    }
}
