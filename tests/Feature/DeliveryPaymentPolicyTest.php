<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CashHandovers;
use App\Services\CodCollections;
use App\Services\LogisticsPayouts;
use App\Services\RiderDeliveries;
use App\Services\RiderWallet;
use App\Services\Settlement;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function partner(): array
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Delivery Hub', 'slug' => 'hub-'.str()->random(8), 'status' => 'approved']);

        return [$owner, $provider];
    }

    private function parcel(LogisticsProvider $provider, string $method = 'cod', ?array $split = null): array
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $shop = $seller->sellers()->create(['name' => 'Shop', 'slug' => 'shop-'.str()->random(8), 'status' => 'approved']);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $profile = Rider::create(['user_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'status' => 'approved', 'is_active' => true]);
        $order = Order::create(['buyer_id' => User::factory()->create(['role' => 'buyer'])->id, 'reference' => 'ZAY-'.str()->random(8), 'total_minor' => 112000, 'payment_method' => $method, 'shipping_address' => ['recipient' => 'Buyer', 'city' => 'Calamba']]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $shop->id, 'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 100000, 'shipping_fee_minor' => $split ? (int) round($split['shipping_paid_by_buyer'] * 100) : 12000,
            'commission_minor' => 10000, 'status' => 'delivered', 'settlement_breakdown' => $split,
        ]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'rider_id' => $profile->id, 'tracking_code' => 'TRK-'.str()->random(8), 'status' => ShipmentWorkflow::stored('delivered')]);

        return [$shipment, $rider, $seller];
    }

    public function test_partner_changes_only_its_rate_and_existing_order_rates_are_preserved(): void
    {
        [$owner, $provider] = $this->partner();
        [, $other] = $this->partner();
        [$first, $rider] = $this->parcel($provider, 'gcash');
        $this->assertSame(40.0, RiderWallet::earningsFor($first));
        $this->actingAs($owner)->patch(route('logistics.account.rider-rate'), ['rider_rate' => '60.55', 'logistics_provider_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame(60.55, $provider->fresh()->riderRate());
        $this->assertSame(40.0, $other->fresh()->riderRate());
        $this->assertSame(40.0, RiderWallet::earningsFor($first->fresh()));
        [$next, $nextRider] = $this->parcel($provider->fresh(), 'gcash');
        $wallet = app(RiderWallet::class);
        $wallet->credit($first, $rider);
        $wallet->credit($first, $rider);
        $wallet->credit($next, $nextRider);
        $this->assertSame(40.0, $wallet->balance($rider));
        $this->assertSame(60.55, $wallet->balance($nextRider));
        foreach (['0', '-1', '120.01', '40.123'] as $invalid) {
            $this->patch(route('logistics.account.rider-rate'), ['rider_rate' => $invalid])->assertSessionHasErrors('rider_rate');
        }
        $this->actingAs($rider)->patch(route('logistics.account.rider-rate'), ['rider_rate' => 50])->assertForbidden();
    }

    public function test_cod_hands_over_the_full_amount_and_is_not_paid_again_in_admin_batches(): void
    {
        [$owner, $provider] = $this->partner();
        [$shipment, $rider] = $this->parcel($provider);
        $amounts = RiderDeliveries::amounts($shipment);
        $this->assertSame(1120.0, $amounts['amount_to_hand_logistics']);
        $this->assertSame(0.0, $amounts['rider_retained_cash']);
        $this->assertNull(app(RiderWallet::class)->credit($shipment, $rider), 'COD earnings need collection confirmation.');
        app(CodCollections::class)->collect($shipment, $rider);
        $this->assertSame(40.0, app(RiderWallet::class)->balance($rider));
        app(CashHandovers::class)->record($provider, $rider, 1120, null, $owner);
        $this->assertDatabaseHas('logistics_cod_settlements', ['shipment_id' => $shipment->id, 'amount_received' => 1120, 'seller_earnings' => 800, 'admin_earnings' => 100, 'logistics_earnings' => 180, 'rider_earnings' => 40]);
        $payouts = app(LogisticsPayouts::class);
        $this->assertSame(0.0, $payouts->summary($payouts->rows($provider))['owed']);
        $this->assertNull($payouts->pay($provider, User::factory()->create(['role' => 'admin']), 'DO-NOT-DUPLICATE'));
        $this->assertDatabaseCount('rider_commissions', 1);
    }

    public function test_prepaid_payout_funds_the_rider_once_within_the_company_gross(): void
    {
        [, $provider] = $this->partner();
        [$shipment, $rider] = $this->parcel($provider, 'gcash');
        app(RiderWallet::class)->credit($shipment, $rider);
        $payouts = app(LogisticsPayouts::class);
        $summary = $payouts->summary($payouts->rows($provider));
        $this->assertSame(220.0, $summary['partner']);
        $this->assertSame(40.0, $summary['rider']);
        $this->assertSame(180.0, $summary['company']);
        $payout = $payouts->pay($provider, User::factory()->create(['role' => 'admin']), 'PREPAID-1');
        $this->assertSame(220.0, $payout->amount);
        $this->assertSame(40.0, $payout->rider_commissions);
        $this->assertSame(180.0, app(\App\Services\Wallet::class)->summary($provider->owner)['credits']);
        $this->assertNull($payouts->pay($provider, User::factory()->create(['role' => 'admin'])));
    }

    public function test_seller_and_admin_voucher_funding_is_preserved_in_cod_allocations(): void
    {
        foreach (['seller', 'admin'] as $payer) {
            [$owner, $provider] = $this->partner();
            $split = Settlement::deliveryBreakdown(1000, 0, $payer === 'admin' ? 120 : 0, $payer === 'seller' ? 120 : 0);
            [$shipment, $rider] = $this->parcel($provider, split: $split);
            app(CodCollections::class)->collect($shipment, $rider);
            app(CashHandovers::class)->record($provider, $rider, 1000, null, $owner);
            $this->assertDatabaseHas('logistics_cod_settlements', ['shipment_id' => $shipment->id, 'seller_earnings' => $payer === 'seller' ? 680 : 800, 'admin_earnings' => $payer === 'admin' ? -20 : 100]);
        }
    }

    public function test_cod_admin_transfer_is_directional_authorized_and_recorded_once(): void
    {
        foreach (['buyer', 'admin'] as $payer) {
            [$owner, $provider] = $this->partner();
            $split = Settlement::deliveryBreakdown(1000, $payer === 'buyer' ? 120 : 0, $payer === 'admin' ? 120 : 0);
            [$shipment, $rider] = $this->parcel($provider, split: $split);
            app(CodCollections::class)->collect($shipment, $rider);
            app(CashHandovers::class)->record($provider, $rider, $split['total_distribution'], null, $owner);
            $settlement = \App\Models\LogisticsCodSettlement::where('shipment_id', $shipment->id)->sole();
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
            [$otherOwner] = $this->partner();
            $this->actingAs($otherOwner)->post(route('logistics.cash.settlements.admin-transfer', $settlement), ['admin_reference' => 'NOT-MY-PAYMENT'])->assertForbidden();
            $this->actingAs($owner)->get(route('logistics.cash'))->assertOk()->assertSee('Admin');
            $actor = $payer === 'admin' ? $admin : $owner;
            $route = $payer === 'admin' ? 'admin.cod-commissions.transfer' : 'logistics.cash.settlements.admin-transfer';
            $this->actingAs($actor)->post(route($route, $settlement), ['admin_reference' => 'TRANSFER-123'])->assertSessionHasNoErrors();
            $this->assertSame('TRANSFER-123', $settlement->fresh()->admin_reference);
            $this->post(route($route, $settlement), ['admin_reference' => 'DUPLICATE'])->assertSessionHasErrors('admin_reference');
            $this->assertDatabaseHas('cod_financial_transactions', ['shipment_id' => $shipment->id, 'type' => 'admin_settlement', 'amount' => $payer === 'admin' ? 20 : 100]);
            $this->assertSame(1, \App\Models\CodFinancialTransaction::where('shipment_id', $shipment->id)->where('type', 'admin_settlement')->count());
        }
    }

    public function test_historical_orders_without_a_snapshot_keep_the_original_allocation(): void
    {
        [, $provider] = $this->partner();
        [$shipment] = $this->parcel($provider);
        \Illuminate\Support\Facades\DB::table('seller_orders')->where('id', $shipment->seller_order_id)->update(['settlement_breakdown' => null]);
        $provider->update(['rider_rate_minor' => 6055]);
        $split = app(Settlement::class)->forShipment($shipment->fresh());
        $this->assertSame(140.0, $split['admin_earnings']);
        $this->assertSame(140.0, $split['logistics_earnings']);
        $this->assertSame(40.0, $split['rider_earnings']);
        $this->assertSame(800.0, $split['seller_earnings']);
    }
}
