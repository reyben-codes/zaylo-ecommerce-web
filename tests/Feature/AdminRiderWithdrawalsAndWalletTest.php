<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
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
            ->assertSee('Rider sweldo (GCash payouts)')
            ->assertSee('Carlos Rider')
            ->assertSee('120.00');

        $this->actingAs($admin)
            ->get(route('admin.wallet'))
            ->assertOk()
            ->assertSee('Admin Wallet')
            ->assertSee('Pending rider sweldo');
    }

    public function test_admin_can_pay_rider_sweldo_with_reference(): void
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

    public function test_admin_can_reject_rider_sweldo_with_reason(): void
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

    public function test_admin_sidebar_counts_and_notifications_include_pending_sweldo(): void
    {
        [$admin, $provider, $riderUser, $withdrawal] = $this->setupRiderWithWithdrawal();

        $counts = app(SidebarCounts::class)->for($admin, 'admin');
        $this->assertArrayHasKey('rider_withdrawals', $counts);
        $this->assertSame(1, $counts['rider_withdrawals'][0]);

        $feed = app(WorkspaceNotificationFeed::class)->forUser($admin);
        $this->assertTrue($feed->contains(fn ($item) => str_contains($item['title'], 'Rider sweldo')));
    }
}
