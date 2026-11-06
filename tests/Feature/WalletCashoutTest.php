<?php

namespace Tests\Feature;

use App\Models\LogisticsPayout;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Models\WalletCashout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Riders (delivery fees) and logistics partners (payouts from ZAYLO) have wallet credits they can cash out to
 * GCash. The transfer is simulated, so it is recorded with a demo reference and the balance goes down.
 */
class WalletCashoutTest extends TestCase
{
    use LegacyOrderTables, RefreshDatabase, SignsInMobile;

    public function test_riders_cash_out_their_delivery_fees_to_gcash_in_the_app(): void
    {
        $this->useLegacyOrderTables();
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => 'Jun Reyes', 'phone' => '09171234567']);
        Rider::create([
            'user_id' => $rider->id, 'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'NBC1234',
            'license_number' => 'S33-45-666666', 'license_expires_on' => now()->addYear()->toDateString(), 'status' => 'approved', 'is_active' => true,
        ]);
        foreach ([60, 60, 60] as $index => $fee) {
            $this->parcel($rider, $fee, 'delivered', $index);
        }
        $this->parcel($rider, 999, 'out_for_delivery', 9); // not delivered yet: no credit

        // The website's rider Earnings page shows the same wallet.
        $this->actingAs($rider)->get(route('courier.earnings'))->assertOk()
            ->assertSee('Delivery fees of the parcels you delivered')->assertSee('&#8369;180.00', false)
            ->assertSee(route('courier.wallet.cash-out'), false);

        $this->signInMobile($rider);

        $this->getJson('/api/mobile/rider/wallet')->assertOk()
            ->assertJson(['available' => true, 'credits' => 180, 'cashed_out' => 0, 'balance' => 180, 'minimum' => 100, 'history' => []])
            ->assertJsonPath('demo_note', 'GCash transfers are simulated in this version: no real money is sent.');

        // More than the balance, too little, or a wrong number is refused.
        $this->postJson('/api/mobile/rider/wallet/cash-out', ['amount' => 200, 'gcash_number' => '09171234567', 'gcash_name' => 'Jun Reyes'])
            ->assertUnprocessable()->assertJsonPath('errors.amount.0', 'You can cash out up to ₱180.00, your wallet balance.');
        $this->postJson('/api/mobile/rider/wallet/cash-out', ['amount' => 50, 'gcash_number' => '09171234567', 'gcash_name' => 'Jun Reyes'])
            ->assertUnprocessable()->assertJsonPath('errors.amount.0', 'The smallest cash-out is ₱100.');
        $this->postJson('/api/mobile/rider/wallet/cash-out', ['amount' => 100, 'gcash_number' => '9171234567', 'gcash_name' => 'Jun Reyes'])
            ->assertUnprocessable()->assertJsonValidationErrors('gcash_number');

        $sent = $this->postJson('/api/mobile/rider/wallet/cash-out', ['amount' => 120.50, 'gcash_number' => '09171234567', 'gcash_name' => 'Jun Reyes'])
            ->assertCreated()
            ->assertJson(['balance' => 59.5, 'cashed_out' => 120.5, 'cashout' => ['amount' => 120.5, 'gcash_number' => '0917 ••• 4567', 'status' => 'sent']])
            ->assertJsonCount(1, 'history');
        $reference = $sent->json('cashout.reference');
        $this->assertStringStartsWith('GCASH-DEMO-', $reference);
        $this->assertStringContainsString($reference, $sent->json('message'));
        $this->assertDatabaseHas('wallet_cashouts', ['user_id' => $rider->id, 'gcash_number' => '09171234567', 'reference' => $reference]);

        // What is left is below the minimum, so nothing more can go out.
        $this->postJson('/api/mobile/rider/wallet/cash-out', ['amount' => 100, 'gcash_number' => '09171234567', 'gcash_name' => 'Jun Reyes'])
            ->assertUnprocessable()->assertJsonPath('errors.amount.0', 'You can cash out up to ₱59.50, your wallet balance.');
        $this->assertSame(1, WalletCashout::count());
    }

    public function test_logistics_partners_cash_out_payouts_from_the_earnings_page(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'name' => 'Ana Cruz']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        LogisticsPayout::create(['logistics_provider_id' => $partner->id, 'parcels' => 5, 'shipping_fees' => 625, 'amount' => 500, 'partner_share' => 0.8, 'paid_at' => now()]);
        $this->actingAs($owner);

        $this->get(route('logistics.earnings'))->assertOk()
            ->assertSee('Wallet')->assertSee('Demo GCash: no real money is sent')
            ->assertSee('Payouts ZAYLO recorded for your company')
            ->assertSee('&#8369;500.00', false)
            ->assertSee(route('logistics.wallet.cash-out'), false);

        $this->post(route('logistics.wallet.cash-out'), ['amount' => 600, 'gcash_number' => '09181234567', 'gcash_name' => 'Ana Cruz'])
            ->assertSessionHasErrors(['amount' => 'You can cash out up to ₱500.00, your wallet balance.']);
        $this->post(route('logistics.wallet.cash-out'), ['amount' => 300, 'gcash_number' => '09181234567', 'gcash_name' => 'Ana Cruz'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $status) => str_starts_with($status, '₱300.00 was sent to GCash 0918 ••• 4567 (demo transfer, reference GCASH-DEMO-'));

        $this->get(route('logistics.earnings'))->assertOk()
            ->assertSee('&#8369;200.00', false)
            ->assertSee('&#8369;300.00 to GCash 0918 ••• 4567', false);

        // Buyers and sellers have no wallet.
        $this->actingAs(User::factory()->create(['role' => 'seller', 'status' => 'active']))
            ->post(route('logistics.wallet.cash-out'), ['amount' => 100, 'gcash_number' => '09181234567', 'gcash_name' => 'X'])->assertForbidden();
    }

    private function parcel(User $rider, float $fee, string $status, int $index): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-WALLET-'.$index, 'user_id' => $buyer->id, 'status' => $status === 'delivered' ? 'delivered' : 'out_for_delivery',
            'subtotal' => 500, 'shipping_fee' => $fee, 'total' => 500 + $fee, 'payment_method' => 'cod', 'recipient_name' => 'Maria Santos',
            'phone' => '09170000000', 'shipping_address' => '12 Rizal St, Calamba, Laguna', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shipments')->insert([
            'order_id' => $orderId, 'courier_id' => $rider->id, 'tracking_number' => 'TRK-WALLET-'.$index, 'status' => $status,
            'delivered_at' => $status === 'delivered' ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
