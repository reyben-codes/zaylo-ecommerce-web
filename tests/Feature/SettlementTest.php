<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use App\Services\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyOrderTables;
use Tests\TestCase;

/**
 * Where the money goes for COD and for GCash paid to the seller, how sellers settle with ZAYLO, and how riders hand
 * their COD cash to the hub.
 */
class SettlementTest extends TestCase
{
    use LegacyOrderTables, RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
        foreach (['2026_11_07_000010_add_seller_vouchers', '2026_11_07_000011_create_rider_cash_handovers'] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
    }

    public function test_every_peso_of_an_order_is_accounted_for(): void
    {
        // COD, ₱1,000 of items, ₱100 shipping, ₱100 off from a voucher the shop joined.
        $cod = (new Settlement)->forOrder($this->order('cod', 'completed', voucher: 100));
        $this->assertSame(1000.0, $cod['buyer_paid']);
        $this->assertSame([100.0, 80.0, 800.0, 120.0], [$cod['commission'], $cod['partner_share'], $cod['seller_keeps'], $cod['zaylo_keeps']]);
        $this->assertSame($cod['buyer_paid'], $cod['seller_keeps'] + $cod['partner_share'] + $cod['zaylo_keeps'], 'The buyer\'s money is fully shared out.');
        $this->assertSame(['rider', 800.0, 0.0], [$cod['collected_by'], $cod['zaylo_owes_seller'], $cod['seller_owes_zaylo']]);

        // GCash: the seller already has the buyer's ₱1,000, so they owe the commission and the shipping the buyer paid.
        $gcash = (new Settlement)->forOrder($this->order('gcash', 'completed', voucher: 100));
        $this->assertSame(['seller', 0.0, 200.0], [$gcash['collected_by'], $gcash['zaylo_owes_seller'], $gcash['seller_owes_zaylo']]);
        $this->assertSame($gcash['buyer_paid'] - $gcash['seller_owes_zaylo'], $gcash['seller_keeps']);

        // ZAYLO's free shipping: the partner still earns its 80%, paid out of ZAYLO's share.
        $free = (new Settlement)->forOrder($this->order('cod', 'completed', shipping: 0, freeShipping: 100));
        $this->assertSame([1000.0, 80.0, 900.0, 20.0], [$free['buyer_paid'], $free['partner_share'], $free['seller_keeps'], $free['zaylo_keeps']]);

        // Nothing moves before the buyer has the order.
        $this->assertSame(0.0, (new Settlement)->forOrder($this->order('cod', 'confirmed'))['zaylo_owes_seller']);
    }

    public function test_sellers_see_their_balance_with_zaylo(): void
    {
        $this->order('cod', 'completed');       // ZAYLO owes ₱900
        $this->order('gcash', 'delivered');     // seller owes ₱200
        $this->order('cod', 'confirmed');       // not complete yet

        $this->actingAs($this->seller)->get(route('seller.settlement'))->assertOk()
            ->assertSee('ZAYLO owes you (COD)')->assertSee('&#8369;900.00', false)->assertSee('&#8369;200.00', false)
            ->assertSee('Payout due to you')->assertSee('&#8369;700.00', false)
            ->assertSee('GCash (to you)');
    }

    public function test_riders_hand_their_cod_cash_to_the_hub_and_shortfalls_are_kept(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => 'Jun Reyes']);
        Rider::create(['user_id' => $rider->id, 'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);
        foreach ([['cod', 1100], ['cod', 600], ['gcash', 900]] as [$method, $total]) {
            $order = $this->order($method, 'delivered', items: $total - 100);
            DB::table('shipments')->insert(['order_id' => $order->id, 'courier_id' => $rider->id, 'tracking_number' => 'TRK-'.$order->id, 'status' => 'delivered', 'delivered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($owner);

        // Only the COD parcels are cash the rider holds (₱1,100 + ₱600); the GCash one was paid to the seller.
        $this->get(route('logistics.cash'))->assertOk()->assertSee('Jun Reyes')->assertSee('&#8369;1,700.00', false)
            ->assertSee('data-sidebar-count="cash" data-sidebar-count-label="riders holding cash" >1', false);

        // ₱50 short: a note is needed, then the shortfall is kept on record.
        $this->post(route('logistics.cash.record', $rider), ['received' => 1650])->assertSessionHasErrors(['note' => 'The cash is ₱50.00 short. Note what the rider said.']);
        $this->post(route('logistics.cash.record', $rider), ['received' => 1650, 'note' => 'Gave change to a buyer'])->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Recorded ₱1,650.00 from Jun Reyes: ₱50.00 short, kept on record.');
        $this->assertDatabaseHas('rider_cash_handovers', ['rider_user_id' => $rider->id, 'parcels' => 2, 'expected' => 1700, 'received' => 1650, 'shortfall' => 50]);

        // Counted once: nothing is left to hand over.
        $this->get(route('logistics.cash'))->assertSee('No rider is holding cash right now.')->assertSee('Gave change to a buyer');
        $this->post(route('logistics.cash.record', $rider), ['received' => 10])->assertSessionHasErrors('received');

        // Another partner cannot record this rider's cash.
        $other = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        LogisticsProvider::create(['user_id' => $other->id, 'name' => 'Swift', 'slug' => 'swift', 'status' => 'approved']);
        $this->actingAs($other)->post(route('logistics.cash.record', $rider), ['received' => 10])->assertForbidden();
    }

    private function order(string $method, string $status, float $items = 1000, float $shipping = 100, float $freeShipping = 0, float $voucher = 0): Order
    {
        $id = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-'.strtoupper(uniqid()), 'user_id' => User::factory()->create(['role' => 'buyer'])->id, 'seller_id' => $this->seller->sellerShop()->id,
            'status' => $status, 'subtotal' => $items, 'shipping_fee' => $shipping, 'shipping_discount' => $freeShipping, 'voucher_discount' => $voucher,
            'total' => $items + $shipping - $voucher, 'payment_method' => $method, 'recipient_name' => 'Maria Santos', 'phone' => '09170000000',
            'shipping_address' => '12 Rizal St, Calamba, Laguna', 'placed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }
}
