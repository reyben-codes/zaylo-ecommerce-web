<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\RiderCommission;
use App\Models\RiderWithdrawal;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Riders earn ₱15 per delivered parcel into a wallet and withdraw ₱100 or more to GCash; their logistics partner
 * sends the money and records the reference, or rejects the request and the amount returns to the wallet.
 */
class RiderWalletTest extends TestCase
{
    use LegacyOrderTables;
    use RefreshDatabase;
    use SignsInMobile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
    }

    /** Credits [$rider] for [$count] delivered parcels. */
    private function earn(User $rider, int $count): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        foreach (range(1, $count) as $_) {
            $orderId = DB::table('orders')->insertGetId([
                'order_number' => 'ZAY-'.str()->random(8), 'user_id' => $buyer->id, 'status' => 'delivered', 'subtotal' => 500,
                'shipping_fee' => 120, 'total' => 620, 'payment_method' => 'cod', 'recipient_name' => 'Maria Santos',
                'phone' => '09170000000', 'shipping_address' => 'Calamba, Laguna', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $shipmentId = DB::table('shipments')->insertGetId([
                'order_id' => $orderId, 'courier_id' => $rider->id, 'tracking_number' => 'TRK-'.$orderId, 'status' => 'delivered',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(RiderWallet::class)->credit(Shipment::findOrFail($shipmentId), $rider);
        }
    }

    public function test_each_delivered_parcel_credits_the_commission_once(): void
    {
        $rider = $this->approvedRider();
        $this->earn($rider, 2);
        $wallet = app(RiderWallet::class);
        $wallet->credit(Shipment::firstOrFail(), $rider); // credited again: nothing changes

        $this->assertSame(2, RiderCommission::count());
        $this->assertSame(30.0, $wallet->balance($rider));
    }

    public function test_a_rider_withdraws_to_gcash_from_the_minimum_up_to_the_balance(): void
    {
        $rider = $this->approvedRider();
        $this->earn($rider, 8); // ₱120
        $this->signInMobile($rider);

        $this->getJson('/api/mobile/rider/wallet')->assertOk()
            ->assertJson(['balance' => 120, 'earned' => 120, 'parcels' => 8, 'commission_per_parcel' => 15, 'min_withdrawal' => 100, 'gcash' => null])
            ->assertJsonCount(8, 'commissions');

        $request = ['amount' => 100, 'gcash_number' => '0917 123 4567', 'gcash_name' => 'Juan Dela Cruz'];
        $this->postJson('/api/mobile/rider/wallet/withdrawals', ['amount' => 99.99] + $request)->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'The minimum withdrawal is ₱100.00.');
        $this->postJson('/api/mobile/rider/wallet/withdrawals', ['amount' => 120.01] + $request)->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'You can withdraw up to ₱120.00.');
        $this->postJson('/api/mobile/rider/wallet/withdrawals', ['gcash_number' => '0817 123 4567'] + $request)->assertUnprocessable()
            ->assertJsonValidationErrors('gcash_number');

        $this->postJson('/api/mobile/rider/wallet/withdrawals', $request)->assertCreated()
            ->assertJsonPath('message', 'Withdrawal requested. ₱100.00 will be sent to GCash 0917 123 4567 once your logistics partner processes it.')
            ->assertJson(['balance' => 20, 'pending' => 100, 'withdrawn' => 0, 'gcash' => ['number' => '09171234567', 'name' => 'Juan Dela Cruz']])
            ->assertJsonPath('withdrawals.0.status', 'pending')
            ->assertJsonPath('withdrawals.0.status_label', 'Processing');
        $this->assertTrue(ActivityLog::where('event', 'mobile.rider.wallet.withdraw')->where('user_id', $rider->id)->exists());

        // The pending request already left the balance, so it cannot be spent twice.
        $this->postJson('/api/mobile/rider/wallet/withdrawals', $request)->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'You can withdraw up to ₱20.00.');
    }

    public function test_the_riders_logistics_partner_sends_or_rejects_each_withdrawal_once(): void
    {
        $rider = $this->approvedRider(['name' => 'Jun Reyes']);
        $this->earn($rider, 20); // ₱300
        $wallet = app(RiderWallet::class);
        $first = $wallet->withdraw($rider, 100, '09171234567', 'Jun Reyes');
        $second = $wallet->withdraw($rider, 150, '09171234567', 'Jun Reyes');
        $partner = $rider->rider->provider->owner;
        $otherPartner = $this->approvedRider()->rider->provider->owner;
        $this->assertSame($rider->rider->logistics_provider_id, $first->logistics_provider_id);

        $this->actingAs($rider)->get(route('logistics.rider-withdrawals'))->assertForbidden();
        // Another partner sees none of this rider's requests and cannot touch them.
        $this->actingAs($otherPartner)->get(route('logistics.rider-withdrawals'))->assertOk()->assertDontSee('Jun Reyes');
        $this->post(route('logistics.rider-withdrawals.pay', $first), ['reference' => 'GC-1'])->assertForbidden();

        $this->actingAs($partner)->get(route('logistics.rider-withdrawals'))->assertOk()
            ->assertSee('Jun Reyes')->assertSee('0917 123 4567')->assertSee('&#8369;250.00', false)->assertSee('Mark as sent');
        $this->post(route('logistics.rider-withdrawals.pay', $first))->assertSessionHasErrors('reference');
        $this->post(route('logistics.rider-withdrawals.pay', $first), ['reference' => 'GC-778899'])
            ->assertSessionHas('status', 'Recorded ₱100.00 sent to Jun Reyes on GCash.');
        $this->assertSame(['paid', 'GC-778899', $partner->id], [$first->fresh()->status, $first->fresh()->reference, $first->fresh()->processed_by]);
        $this->assertTrue(ActivityLog::where('event', 'logistics.rider-withdrawals.pay')->exists());
        $this->post(route('logistics.rider-withdrawals.reject', $first), ['rejection_reason' => 'Too late'])->assertSessionHasErrors('withdrawal');

        // Rejecting returns the amount to the wallet.
        $this->assertSame(50.0, $wallet->balance($rider));
        $this->post(route('logistics.rider-withdrawals.reject', $second), ['rejection_reason' => 'The GCash name does not match the number.'])
            ->assertSessionHas('status', "Rejected the withdrawal; ₱150.00 is back in Jun Reyes's wallet.");
        $this->assertSame(200.0, $wallet->balance($rider));

        $this->signInMobile($rider);
        $this->getJson('/api/mobile/rider/wallet')->assertOk()
            ->assertJson(['balance' => 200, 'pending' => 0, 'withdrawn' => 100])
            ->assertJsonPath('withdrawals.0.status_label', 'Rejected')
            ->assertJsonPath('withdrawals.0.rejection_reason', 'The GCash name does not match the number.')
            ->assertJsonPath('withdrawals.1.status_label', 'Sent to GCash')
            ->assertJsonPath('withdrawals.1.reference', 'GC-778899');
        $this->assertSame(2, RiderWithdrawal::count());
    }

    public function test_only_riders_use_the_wallet(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($buyer)->getJson('/api/mobile/rider/wallet')->assertForbidden();
    }
}
