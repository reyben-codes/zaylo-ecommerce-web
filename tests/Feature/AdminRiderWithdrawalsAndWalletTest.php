<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\EscrowPayment;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderWithdrawal;
use App\Models\User;
use App\Services\RiderWallet;
use App\Services\SidebarCounts;
use App\Services\WorkspaceNotificationFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRiderWithdrawalsAndWalletTest extends TestCase
{
    use RefreshDatabase;

    private function setupRiderWithWithdrawal(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create([
            'user_id' => $owner->id,
            'name' => 'Logistics One',
            'slug' => 'logistics-one-'.str()->random(6),
            'status' => 'approved',
        ]);
        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => 'Rider Carlos']);
        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $provider->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $withdrawal = RiderWithdrawal::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $provider->id,
            'amount' => 120.00,
            'gcash_number' => '09171234567',
            'gcash_name' => 'Carlos Rider',
            'status' => RiderWithdrawal::PENDING,
        ]);

        return [$admin, $provider, $riderUser, $withdrawal];
    }

    public function test_admin_can_view_rider_withdrawals_and_wallet(): void
    {
        [$admin, $provider, $riderUser, $withdrawal] = $this->setupRiderWithWithdrawal();

        $this->actingAs($admin)
            ->get(route('admin.rider-withdrawals'))
            ->assertOk()
            ->assertSee('Rider payouts (GCash)')
            ->assertSee('Carlos Rider')
            ->assertSee('120.00');

        $this->actingAs($admin)
            ->get(route('admin.wallet'))
            ->assertOk()
            ->assertSee('Admin Wallet')
            ->assertSee('Pending rider payouts');
    }

    public function test_admin_wallet_converts_held_and_released_payment_totals_to_pesos(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        foreach ([['held', 123456], ['held', 10000], ['released', 65432], ['pending', 99999]] as $index => [$status, $amount]) {
            $order = Order::create([
                'buyer_id' => $buyer->id,
                'reference' => 'WALLET-TEST-'.$index,
                'total_minor' => $amount,
                'payment_method' => 'gcash',
                'shipping_address' => ['city' => 'Calamba'],
            ]);
            EscrowPayment::create([
                'order_id' => $order->id,
                'amount_minor' => $amount,
                'status' => $status,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.wallet'))
            ->assertOk()
            ->assertViewHas('escrowHeld', 1334.56)
            ->assertViewHas('escrowReleased', 654.32)
            ->assertSee('1,334.56');
    }

    public function test_admin_can_pay_rider_payout_with_reference(): void
    {
        [$admin, $provider, $riderUser, $withdrawal] = $this->setupRiderWithWithdrawal();

        $this->actingAs($admin)
            ->post(route('admin.rider-withdrawals.pay', $withdrawal), [
                'reference' => 'REF20261115AB',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $withdrawal->refresh();
        $this->assertSame(RiderWithdrawal::RELEASED, $withdrawal->status);
        $this->assertSame('REF20261115AB', $withdrawal->reference);
        $this->assertSame($admin->id, $withdrawal->processed_by);
    }

    public function test_admin_can_reject_rider_payout_with_reason(): void
    {
        [$admin, $provider, $riderUser, $withdrawal] = $this->setupRiderWithWithdrawal();

        $this->actingAs($admin)
            ->post(route('admin.rider-withdrawals.reject', $withdrawal), [
                'rejection_reason' => 'Invalid GCash recipient name',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $withdrawal->refresh();
        $this->assertSame(RiderWithdrawal::REJECTED, $withdrawal->status);
        $this->assertSame('Invalid GCash recipient name', $withdrawal->rejection_reason);
    }

    public function test_admin_sidebar_counts_and_notifications_include_pending_payouts(): void
    {
        [$admin, $provider, $riderUser, $withdrawal] = $this->setupRiderWithWithdrawal();

        $counts = app(SidebarCounts::class)->for($admin, 'admin');
        $this->assertArrayHasKey('rider_withdrawals', $counts);
        $this->assertSame(1, $counts['rider_withdrawals'][0]);

        $feed = app(WorkspaceNotificationFeed::class)->forUser($admin);
        $this->assertTrue($feed->contains(fn ($item) => str_contains($item['title'], 'Rider payout')));
    }
}
