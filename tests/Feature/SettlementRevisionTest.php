<?php

namespace Tests\Feature;

use App\Models\AccountUpdate;
use App\Models\EscrowPayment;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\RiderWithdrawal;
use App\Models\User;
use App\Services\AccountUpdates;
use App\Services\EscrowPayments;
use App\Services\OrderReceipts;
use App\Services\RiderWallet;
use App\Services\SellerWallet;
use App\Services\Settlement;
use App\Support\BuyerOrderTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class SettlementRevisionTest extends TestCase
{
    use RefreshDatabase, SignsInMobile, \Tests\Support\LegacyOrderTables;

    private function parcel(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Settlement Partner', 'slug' => 'settlement-partner', 'status' => 'approved']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'ZAY-ESCROW-1', 'total_minor' => 112000, 'payment_method' => 'gcash', 'shipping_address' => ['city' => 'Calamba']]);
        $split = Settlement::deliveryBreakdown(1000, 120);
        $order->update(['settlement_breakdown' => $split]);
        $sellerOrder = $order->sellerOrders()->create(['seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 100000,
            'shipping_fee_minor' => 12000, 'commission_minor' => 10000, 'status' => 'pending', 'settlement_breakdown' => $split]);
        $shipment = $sellerOrder->shipment()->create(['logistics_provider_id' => $provider->id, 'tracking_code' => 'ESCROW-TRACK', 'status' => 'unassigned']);
        $order->payment()->create(['method' => 'gcash', 'provider' => 'gcash', 'amount_minor' => 112000, 'status' => 'pending']);
        config(['escrow.secret_key' => 'sk_test_fixture', 'escrow.webhook_secret' => 'webhook-secret', 'escrow.live' => false, 'escrow.admin_user_id' => $admin->id]);
        $escrow = EscrowPayments::reserve($order);
        $escrow->update(['checkout_session' => 'cs_fixture']);
        return compact('buyer', 'seller', 'admin', 'owner', 'provider', 'order', 'sellerOrder', 'shipment', 'escrow');
    }

    private function event(array $fixture, int $amount = 112000): array
    {
        return ['data' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => ['id' => 'cs_fixture', 'attributes' => [
            'reference_number' => $fixture['order']->order_number, 'payments' => [['id' => 'pay_fixture', 'attributes' => [
                'status' => 'paid', 'amount' => $amount, 'currency' => 'PHP', 'fee' => 2500,
            ]]],
        ]]]];
    }

    public function test_signed_capture_moves_to_ship_and_replays_do_not_duplicate_credits(): void
    {
        $f = $this->parcel();
        $f['order']->update(['status' => 'awaiting_payment', 'placed_at' => null]);
        $body = json_encode($this->event($f));
        $time = time();
        $header = 't='.$time.',te='.hash_hmac('sha256', $time.'.'.$body, 'webhook-secret');
        $this->call('POST', '/api/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $header], $body)->assertOk();
        app(EscrowPayments::class)->capture($this->event($f));
        $this->assertSame('held', $f['escrow']->fresh()->status);
        $this->assertSame('paid', $f['order']->payment->fresh()->status);
        $this->assertSame('placed', $f['order']->fresh()->status);
        $this->assertNotNull($f['order']->fresh()->placed_at);
        $counts = BuyerOrderTabs::counts($f['buyer']->orders()->getQuery());
        $this->assertSame(0, $counts['to_pay']);
        $this->assertSame(1, $counts['to_ship']);
        $this->assertDatabaseCount('settlement_wallet_entries', 0);
        $this->assertSame(1, AccountUpdate::where('user_id', $f['buyer']->id)->where('event_key', 'escrow-held-'.$f['escrow']->id)->count());
    }

    public function test_wrong_amount_and_unsigned_events_cannot_pay_an_order(): void
    {
        $f = $this->parcel();
        $this->postJson('/api/webhooks/paymongo', $this->event($f))->assertUnauthorized();
        try { app(EscrowPayments::class)->capture($this->event($f, 1)); $this->fail('Amount mismatch was accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(422, $error->getStatusCode()); }
        $this->assertSame('pending', $f['escrow']->fresh()->status);
    }

    public function test_delivery_confirmation_releases_wallets_once_after_fees(): void
    {
        $f = $this->parcel();
        app(EscrowPayments::class)->capture($this->event($f));
        $f['sellerOrder']->update(['status' => 'delivered', 'delivered_at' => now()]);
        app(OrderReceipts::class)->confirm($f['order']->fresh(), $f['buyer']);
        app(EscrowPayments::class)->release($f['order']->fresh());
        $this->assertSame('released', $f['escrow']->fresh()->status);
        $this->assertDatabaseCount('settlement_wallet_entries', 3);
        $this->assertEquals(109500, DB::table('settlement_wallet_entries')->sum('amount_minor'));
        $this->assertEquals(800, app(SellerWallet::class)->balance($f['seller']));
        $this->assertSame(1, BuyerOrderTabs::counts($f['buyer']->orders()->getQuery())['delivered']);
    }

    public function test_no_release_before_receipt_and_pending_payments_are_to_pay(): void
    {
        $f = $this->parcel();
        $this->assertSame(1, BuyerOrderTabs::counts($f['buyer']->orders()->getQuery())['to_pay']);
        $this->expectException(ValidationException::class);
        app(EscrowPayments::class)->release($f['order']);
    }

    public function test_unread_updates_are_scoped_deduplicated_and_read_only_through_seen_id(): void
    {
        $one = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $two = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $updates = app(AccountUpdates::class);
        $updates->send($one->id, 'one', 'wallet', 'Settled', 'Payment settled');
        $updates->send($one->id, 'one', 'wallet', 'Settled', 'Payment settled');
        $through = AccountUpdate::max('id');
        $updates->send($one->id, 'two', 'wallet', 'Settled again', 'Another payment settled');
        $updates->read($two, 'wallet', $through);
        $this->assertSame(2, $updates->counts($one)['wallet']);
        $updates->read($one, 'wallet', $through);
        $this->assertSame(1, $updates->counts($one)['wallet']);
        $this->assertSame([], $updates->counts($two));
    }

    public function test_salary_requires_logistics_release_then_rider_receipt(): void
    {
        $rider = $this->approvedRider();
        $owner = $rider->rider->provider->owner;
        $withdrawal = RiderWithdrawal::create(['user_id' => $rider->id, 'logistics_provider_id' => $rider->rider->logistics_provider_id,
            'amount' => 90, 'gcash_number' => '09171234567', 'gcash_name' => 'Test Rider', 'status' => 'pending']);
        $wallet = app(RiderWallet::class);
        try { $wallet->confirmReceived($withdrawal, $rider); $this->fail('Unreleased salary was confirmed.'); }
        catch (ValidationException) { $this->assertSame('pending', $withdrawal->fresh()->status); }
        $wallet->markPaid($withdrawal, $owner, 'REF20261115AB');
        $this->assertSame('released', $withdrawal->fresh()->status);
        $this->signInMobile($rider)->postJson('/api/mobile/rider/wallet/withdrawals/'.$withdrawal->id.'/received')->assertOk();
        $wallet->confirmReceived($withdrawal, $rider);
        $this->assertSame('paid', $withdrawal->fresh()->status);
        $this->assertNotNull($withdrawal->fresh()->received_at);
        $this->assertSame(1, AccountUpdate::where('user_id', $rider->id)->where('event_key', 'salary-received-'.$withdrawal->id)->count());
    }

    public function test_checkout_session_is_server_priced_and_reused(): void
    {
        $f = $this->parcel();
        $f['escrow']->update(['checkout_session' => null]);
        Http::preventStrayRequests();
        Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'cs_new', 'attributes' => [
            'livemode' => false, 'checkout_url' => 'https://checkout.paymongo.com/cs_new',
        ]]])]);
        $service = app(EscrowPayments::class);
        $this->assertSame('https://checkout.paymongo.com/cs_new', $service->checkout($f['order']));
        $this->assertSame('https://checkout.paymongo.com/cs_new', $service->checkout($f['order']));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['data']['attributes']['line_items'][0]['amount'] === 112000
            && $request['data']['attributes']['payment_method_types'] === ['gcash']
            && $request->hasHeader('Idempotency-Key', 'zaylo-order-'.$f['order']->id));
        $this->assertSame('pending', $f['escrow']->fresh()->status);
    }

    public function test_open_dispute_blocks_release_and_sandbox_money_cannot_be_withdrawn_as_live(): void
    {
        $f = $this->parcel();
        app(EscrowPayments::class)->capture($this->event($f));
        $f['sellerOrder']->update(['status' => 'completed']);
        $case = $f['order']->disputes()->create(['opened_by' => $f['buyer']->id, 'category' => 'damaged', 'reason' => 'Damaged on arrival', 'status' => 'open']);
        try { app(EscrowPayments::class)->release($f['order']->fresh()); $this->fail('Disputed payment was released.'); }
        catch (ValidationException) { $this->assertDatabaseCount('settlement_wallet_entries', 0); }
        $case->update(['status' => 'resolved']);
        app(EscrowPayments::class)->release($f['order']->fresh());
        $this->assertEquals(800, app(SellerWallet::class)->balance($f['seller']));
        config(['escrow.live' => true]);
        $this->assertEquals(0, app(SellerWallet::class)->balance($f['seller']));
    }

    public function test_each_leg_credits_only_at_its_own_milestone_once(): void
    {
        $f = $this->parcel();
        $rider = $this->approvedRider();
        $quote = \App\Services\RiderLegEarnings::calculate(2, 5, 1, 120,
            ['base_per_leg' => 10, 'per_km' => 2, 'per_kg' => 3, 'shipping_budget_percent' => 80]);
        $f['shipment']->update(['logistics_provider_id' => $rider->rider->logistics_provider_id,
            'rider_id' => $rider->rider->id, 'pickup_courier_id' => $rider->id, 'rider_fee_quote' => $quote,
            'status' => 'delivered', 'pickup_scanned_at' => now(), 'hub_received_at' => now()]);
        $earnings = app(\App\Services\RiderLegEarnings::class);
        $this->assertNull($earnings->credit($f['shipment'], $rider, 'pickup'));
        $this->assertNull($earnings->credit($f['shipment'], $rider, 'delivery'));
        $f['shipment']->update(['rider_handover_confirmed_at' => now(), 'delivery_proof_path' => 'proof/test.jpg']);
        foreach (range(1, 2) as $_) {
            $earnings->credit($f['shipment'], $rider, 'pickup');
            $earnings->credit($f['shipment'], $rider, 'delivery');
        }
        $this->assertDatabaseCount('rider_leg_commissions', 2);
        $this->assertEquals(40, app(RiderWallet::class)->balance($rider));
    }

    public function test_handover_endpoint_rejects_unscanned_and_other_riders_parcels(): void
    {
        $f = $this->parcel();
        $rider = $this->approvedRider();
        $f['shipment']->update(['logistics_provider_id' => $rider->rider->logistics_provider_id, 'pickup_courier_id' => $rider->id]);
        $this->signInMobile($rider)->postJson('/api/mobile/rider/parcels/'.$f['shipment']->id.'/handover')->assertUnprocessable();
        $f['shipment']->update(['pickup_scanned_at' => now(), 'status' => 'picked_up']);
        $this->postJson('/api/mobile/rider/parcels/'.$f['shipment']->id.'/handover')->assertOk();
        $this->assertNotNull($f['shipment']->fresh()->rider_handover_confirmed_at);
        $other = $this->approvedRider();
        $this->signInMobile($other)->postJson('/api/mobile/rider/parcels/'.$f['shipment']->id.'/handover')->assertForbidden();
    }

    public function test_seller_withdrawals_reserve_balance_and_require_real_reference_format(): void
    {
        $f = $this->parcel();
        app(EscrowPayments::class)->capture($this->event($f));
        $f['sellerOrder']->update(['status' => 'completed']);
        app(EscrowPayments::class)->release($f['order']->fresh());
        $wallet = app(SellerWallet::class);
        $withdrawal = $wallet->request($f['seller'], ['amount' => 500, 'method' => 'gcash', 'account_number' => '09171234567', 'account_name' => 'Test Seller']);
        $this->assertEquals(300, $wallet->balance($f['seller']));
        try { $wallet->sent($withdrawal, $f['admin'], 'invalid'); $this->fail('Invalid transfer reference accepted.'); }
        catch (ValidationException) { $this->assertSame('pending', $withdrawal->fresh()->status); }
        $wallet->sent($withdrawal, $f['admin'], 'REF20261115AB');
        $this->assertSame('paid', $withdrawal->fresh()->status);
        $this->assertEquals(300, $wallet->balance($f['seller']));
    }

    public function test_new_wallet_pages_render_for_their_owners_and_android_can_return_from_gateway(): void
    {
        $f = $this->parcel();
        $this->actingAs($f['seller'])->get('/seller/wallet')->assertOk()->assertSee('Available: PHP');
        $this->get('/seller/orders')->assertOk()->assertSee('Waiting for the buyer to authorize gateway payment.');
        $this->actingAs($f['admin'])->get('/admin/seller-withdrawals')->assertOk()->assertSee('Gateway payment holds');
        $this->actingAs($f['buyer'])->get('/buyer/orders')->assertOk()->assertSee('To pay')->assertSee('data-order-count=', false);
        $this->withHeader('User-Agent', 'Android')->get('/payments/return/'.$f['order']->id)->assertOk()->assertSee('Check your payment status');
    }

    public function test_expired_return_window_releases_escrow_and_late_cancelled_payment_needs_review(): void
    {
        $f = $this->parcel();
        app(EscrowPayments::class)->capture($this->event($f));
        $f['sellerOrder']->update(['status' => 'delivered', 'delivered_at' => now()->subHours(25)]);
        $this->assertSame(1, app(OrderReceipts::class)->autoConfirmDue());
        $this->assertSame('released', $f['escrow']->fresh()->status);
    }

    public function test_late_gateway_payment_cannot_revive_a_cancelled_order(): void
    {
        $f = $this->parcel();
        $f['sellerOrder']->update(['status' => 'cancelled']);
        app(EscrowPayments::class)->capture($this->event($f));
        $this->assertSame('review_required', $f['escrow']->fresh()->status);
        $this->assertSame('cancelled', $f['order']->fresh()->status);
        $this->assertDatabaseCount('settlement_wallet_entries', 0);
    }

    public function test_road_and_weight_quote_is_saved_once_and_keeps_its_original_rates(): void
    {
        $f = $this->parcel();
        $f['provider']->update(['hub_latitude' => 14.22, 'hub_longitude' => 121.16]);
        $f['order']->update(['delivery_latitude' => 14.24, 'delivery_longitude' => 121.17]);
        $f['seller']->addresses()->create(['recipient_name' => 'Seller', 'phone' => '09171234567', 'line1' => '1 Seller St',
            'barangay' => 'Test Barangay', 'city' => 'Calamba', 'province' => 'Laguna', 'country_code' => 'PH', 'latitude' => 14.21, 'longitude' => 121.15]);
        $product = \App\Models\Product::create(['seller_id' => $f['sellerOrder']->seller_id, 'name' => 'Weighted Parcel',
            'category_id' => \App\Models\Category::create(['name' => 'Storage bins', 'slug' => 'storage-bins'])->id,
            'price' => 1000, 'stock' => 3, 'weight_grams' => 1000]);
        $f['sellerOrder']->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id,
            'product_name' => 'Weighted Parcel', 'variant_name' => 'Default', 'quantity' => 2, 'unit_price_minor' => 100000]);
        Http::preventStrayRequests();
        Http::fake(['router.project-osrm.org/*' => Http::response(['code' => 'Ok', 'routes' => [['distance' => 5000]]])]);
        $service = app(\App\Services\RiderLegEarnings::class);
        $quote = $service->quote($f['shipment']->fresh());
        $this->assertEquals(2, $quote['weight_kg']);
        $this->assertEquals(26, $quote['pickup_amount']);
        config(['rider_fees.per_km' => 999]);
        $this->assertEquals($quote, $service->quote($f['shipment']->fresh()));
        Http::assertSentCount(2);
    }

    public function test_pending_commission_retries_after_setup_and_routing_are_fixed_without_duplicate_credit(): void
    {
        $f = $this->parcel();
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $f['shipment']->update(['pickup_courier_id' => $rider->id, 'pickup_scanned_at' => now(),
            'rider_handover_confirmed_at' => now(), 'hub_received_at' => now()]);
        $service = app(\App\Services\RiderLegEarnings::class);
        Http::preventStrayRequests();
        $this->assertNull($service->quoteOrDefer($f['shipment']->fresh()));
        $this->assertSame(0, $service->reconcile());
        $this->assertDatabaseCount('rider_leg_commissions', 0);
        $this->assertSame(1, AccountUpdate::where('user_id', $f['owner']->id)
            ->where('event_key', 'commission-setup-'.$f['shipment']->id)->count());
        $f['provider']->update(['hub_latitude' => 14.22, 'hub_longitude' => 121.16]);
        $f['order']->update(['delivery_latitude' => 14.24, 'delivery_longitude' => 121.17]);
        $f['seller']->addresses()->create(['recipient_name' => 'Seller', 'phone' => '09171234567', 'line1' => '1 Seller St',
            'barangay' => 'Test Barangay', 'city' => 'Calamba', 'province' => 'Laguna', 'country_code' => 'PH', 'latitude' => 14.21, 'longitude' => 121.15]);
        $product = \App\Models\Product::create(['seller_id' => $f['sellerOrder']->seller_id, 'name' => 'Weighted Parcel',
            'category_id' => \App\Models\Category::create(['name' => 'Storage bins', 'slug' => 'storage-bins'])->id,
            'price' => 1000, 'stock' => 3, 'weight_grams' => 1000]);
        $f['sellerOrder']->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id,
            'product_name' => 'Weighted Parcel', 'variant_name' => 'Default', 'quantity' => 2, 'unit_price_minor' => 100000]);
        $routingOffline = true;
        Http::fake(function () use (&$routingOffline) {
            if ($routingOffline) throw new \Illuminate\Http\Client\ConnectionException('Routing offline');
            return Http::response(['code' => 'Ok', 'routes' => [['distance' => 5000]]]);
        });
        $this->assertSame(0, $service->reconcile());
        $this->assertDatabaseCount('rider_leg_commissions', 0);
        $routingOffline = false;
        $this->assertSame(1, $service->reconcile());
        $this->assertSame(0, $service->reconcile());
        $this->assertDatabaseCount('rider_leg_commissions', 1);
        $this->assertDatabaseHas('rider_leg_commissions', ['shipment_id' => $f['shipment']->id, 'user_id' => $rider->id, 'leg' => 'pickup', 'amount' => 26]);
    }

    public function test_legacy_order_schema_uses_the_same_verified_release_and_wallet_rules(): void
    {
        $this->useLegacyOrderTables();
        \Illuminate\Support\Facades\Schema::table('orders', fn ($table) => $table->json('settlement_breakdown')->nullable());
        \Illuminate\Support\Facades\Schema::table('shipments', fn ($table) => $table->foreignId('logistics_provider_id')->nullable());
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Legacy Partner', 'slug' => 'legacy-partner', 'status' => 'approved']);
        $order = Order::create(['user_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'ZAY-LEGACY', 'status' => 'placed',
            'subtotal' => 1000, 'shipping_fee' => 120, 'total' => 1120, 'payment_method' => 'gcash', 'recipient_name' => 'Buyer', 'phone' => '09171234567',
            'shipping_address' => 'Calamba, Laguna', 'settlement_breakdown' => Settlement::deliveryBreakdown(1000, 120)]);
        $order->payment()->create(['amount' => 1120, 'status' => 'pending', 'provider' => 'gcash']);
        $order->shipment()->create(['tracking_number' => 'LEGACY-PARCEL', 'logistics_provider_id' => $provider->id, 'status' => 'ready']);
        config(['escrow.live' => false, 'escrow.admin_user_id' => $admin->id]);
        $escrow = EscrowPayments::reserve($order);
        $escrow->update(['checkout_session' => 'cs_fixture']);
        app(EscrowPayments::class)->capture($this->event(['order' => $order]));
        $order->update(['status' => 'completed']);
        app(EscrowPayments::class)->release($order->fresh());
        $this->assertSame('released', $escrow->fresh()->status);
        $this->assertEquals(800, app(SellerWallet::class)->balance($seller));
    }
}
