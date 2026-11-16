<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderCommission;
use App\Models\RiderLegCommission;
use App\Models\Shipment;
use App\Models\User;
use App\Services\EscrowPayments;
use App\Services\OrderReceipts;
use App\Services\RiderDeliveries;
use App\Services\RiderWallet;
use App\Services\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class RiderGcashEarningsWalletTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    private function createGcashDeliveryFixture(?array $quote = null): array
    {
        Storage::fake('local');

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create([
            'user_id' => $owner->id,
            'name' => 'FastLogistics Hub',
            'slug' => 'fast-logistics-'.str()->random(6),
            'status' => 'approved',
        ]);

        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => 'Flash Rider']);
        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'reference' => 'ZAY-GCASH-'.str()->random(6),
            'total_minor' => 112000,
            'payment_method' => 'gcash',
            'shipping_address' => ['city' => 'Calamba', 'province' => 'Laguna'],
            'status' => 'placed',
        ]);

        $split = Settlement::deliveryBreakdown(1000, 120);
        $order->update(['settlement_breakdown' => $split]);

        $sellerOrder = $order->sellerOrders()->create([
            'seller_id' => $seller->sellerShop()->id,
            'subtotal_minor' => 100000,
            'shipping_fee_minor' => 12000,
            'commission_minor' => 10000,
            'status' => 'shipped',
            'settlement_breakdown' => $split,
        ]);

        $shipment = $sellerOrder->shipment()->create([
            'logistics_provider_id' => $provider->id,
            'tracking_code' => 'TRK-GCASH-'.str()->random(6),
            'status' => 'out_for_delivery',
            'rider_id' => $rider->id,
            'rider_fee_quote' => $quote,
        ]);

        $order->payment()->create([
            'method' => 'gcash',
            'provider' => 'gcash',
            'amount_minor' => 112000,
            'status' => 'paid',
        ]);

        config([
            'escrow.secret_key' => 'sk_test_fixture',
            'escrow.webhook_secret' => 'webhook-secret',
            'escrow.live' => false,
            'escrow.admin_user_id' => $admin->id,
        ]);

        $escrow = EscrowPayments::reserve($order);
        $escrow->update(['status' => 'held']);

        return compact('buyer', 'seller', 'admin', 'owner', 'provider', 'riderUser', 'rider', 'order', 'sellerOrder', 'shipment', 'escrow');
    }

    public function test_delivering_gcash_order_via_scan_credits_rider_wallet_balance_immediately(): void
    {
        $f = $this->createGcashDeliveryFixture();
        $rider = $f['riderUser'];
        $shipment = $f['shipment'];
        $code = RiderDeliveries::labelCode($shipment);

        $wallet = app(RiderWallet::class);
        $this->assertEquals(0.0, $wallet->balance($rider));

        $photo = UploadedFile::fake()->image('proof.jpg');
        $this->signInMobile($rider)
            ->postJson('/api/mobile/rider/deliveries/scan', [
                'code' => $code,
                'photo' => $photo,
            ])
            ->assertOk()
            ->assertJsonPath('step', 'delivered');

        $this->assertEquals(40.0, $wallet->balance($rider));
        $this->assertDatabaseHas('rider_commissions', [
            'shipment_id' => $shipment->id,
            'user_id' => $rider->id,
            'amount' => 40.0,
        ]);

        // Mobile rider wallet API endpoint returns credited balance
        $walletResponse = $this->signInMobile($rider)->getJson('/api/mobile/rider/wallet')->assertOk();
        $walletResponse->assertJsonPath('balance', 40);
        $walletResponse->assertJsonPath('earned', 40);
        $walletResponse->assertJsonCount(1, 'commissions');
        $walletResponse->assertJsonPath('commissions.0.amount', 40);

        // Mobile rider dashboard and earnings reflect credited delivery fee
        $this->getJson('/api/mobile/rider/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.earnings_today', 40);

        $this->getJson('/api/mobile/rider/earnings')
            ->assertOk()
            ->assertJsonPath('periods.all.earnings', 40);
    }

    public function test_delivering_gcash_order_via_advance_credits_rider_wallet_balance(): void
    {
        $f = $this->createGcashDeliveryFixture();
        $rider = $f['riderUser'];
        $shipment = $f['shipment'];

        $wallet = app(RiderWallet::class);
        $this->assertEquals(0.0, $wallet->balance($rider));

        app(RiderDeliveries::class)->advance($shipment, $rider, 'delivered', scanned: true);

        $this->assertEquals(40.0, $wallet->balance($rider));
        $this->assertDatabaseHas('rider_commissions', [
            'shipment_id' => $shipment->id,
            'user_id' => $rider->id,
            'amount' => 40.0,
        ]);
    }

    public function test_delivering_gcash_order_with_leg_fee_quote_credits_leg_commission(): void
    {
        $quote = [
            'policy_version' => 3,
            'pickup_km' => 2.5,
            'delivery_km' => 4.0,
            'weight_kg' => 1.2,
            'pickup_amount' => 15.0,
            'delivery_amount' => 25.0,
            'total' => 40.0,
            'rates' => ['base_per_leg' => 10, 'per_km' => 2, 'per_kg' => 3, 'shipping_budget_percent' => 80],
        ];

        $f = $this->createGcashDeliveryFixture($quote);
        $rider = $f['riderUser'];
        $shipment = $f['shipment'];
        $shipment->update([
            'delivery_scanned_at' => now(),
            'delivery_handover_confirmed_at' => now(),
        ]);
        $code = RiderDeliveries::labelCode($shipment);

        $wallet = app(RiderWallet::class);
        $this->assertEquals(0.0, $wallet->balance($rider));

        $photo = UploadedFile::fake()->image('proof.jpg');
        $this->signInMobile($rider)
            ->postJson('/api/mobile/rider/deliveries/scan', [
                'code' => $code,
                'photo' => $photo,
            ])
            ->assertOk()
            ->assertJsonPath('step', 'delivered');

        $this->assertEquals(25.0, $wallet->balance($rider));
        $this->assertDatabaseHas('rider_leg_commissions', [
            'shipment_id' => $shipment->id,
            'user_id' => $rider->id,
            'leg' => 'delivery',
            'amount' => 25.0,
        ]);

        $walletResponse = $this->signInMobile($rider)->getJson('/api/mobile/rider/wallet')->assertOk();
        $walletResponse->assertJsonPath('balance', 25);
    }

    public function test_escrow_release_guarantees_rider_wallet_balance_is_credited(): void
    {
        $f = $this->createGcashDeliveryFixture();
        $rider = $f['riderUser'];
        $shipment = $f['shipment'];
        $order = $f['order'];
        $sellerOrder = $f['sellerOrder'];

        $shipment->update(['status' => 'delivered']);
        $sellerOrder->update(['status' => 'completed']);
        $order->update(['status' => 'completed']);

        $wallet = app(RiderWallet::class);
        $this->assertEquals(0.0, $wallet->balance($rider));

        app(EscrowPayments::class)->release($order->fresh());

        $this->assertEquals(40.0, $wallet->balance($rider));
        $this->assertDatabaseHas('rider_commissions', [
            'shipment_id' => $shipment->id,
            'user_id' => $rider->id,
            'amount' => 40.0,
        ]);
    }

    public function test_buyer_confirm_delivery_guarantees_rider_wallet_balance_is_credited(): void
    {
        $f = $this->createGcashDeliveryFixture();
        $rider = $f['riderUser'];
        $shipment = $f['shipment'];
        $order = $f['order'];
        $sellerOrder = $f['sellerOrder'];
        $buyer = $f['buyer'];

        $shipment->update(['status' => 'delivered']);
        $sellerOrder->update(['status' => 'delivered']);

        $wallet = app(RiderWallet::class);
        $this->assertEquals(0.0, $wallet->balance($rider));

        app(OrderReceipts::class)->confirm($order->fresh(), $buyer);

        $this->assertEquals(40.0, $wallet->balance($rider));
        $this->assertDatabaseHas('rider_commissions', [
            'shipment_id' => $shipment->id,
            'user_id' => $rider->id,
            'amount' => 40.0,
        ]);
    }

    public function test_rider_can_withdraw_gcash_earnings_from_wallet(): void
    {
        $f = $this->createGcashDeliveryFixture();
        $rider = $f['riderUser'];
        $shipment = $f['shipment'];

        app(RiderDeliveries::class)->advance($shipment, $rider, 'delivered', scanned: true);
        $wallet = app(RiderWallet::class);
        $this->assertEquals(40.0, $wallet->balance($rider));

        $this->signInMobile($rider)
            ->postJson('/api/mobile/rider/wallet/withdrawals', [
                'amount' => 40.0,
                'gcash_number' => '0917 111 2233',
                'gcash_name' => 'Flash Rider',
            ])
            ->assertCreated()
            ->assertJsonPath('balance', 0)
            ->assertJsonPath('pending', 40);

        $this->assertEquals(0.0, $wallet->balance($rider));
        $this->assertDatabaseHas('rider_withdrawals', [
            'user_id' => $rider->id,
            'amount' => 40.0,
            'status' => 'pending',
            'gcash_number' => '09171112233',
            'gcash_name' => 'Flash Rider',
        ]);
    }
}
