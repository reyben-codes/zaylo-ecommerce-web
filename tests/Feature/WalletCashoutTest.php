<?php

namespace Tests\Feature;

use App\Models\LogisticsPayout;
use App\Models\LogisticsProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Logistics partners cash out their share of ZAYLO's payouts (after their riders' commissions) to GCash. The
 * transfer is simulated, so it is recorded with a demo reference and the balance goes down. Riders have their own
 * wallet (RiderWalletTest).
 */
class WalletCashoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_partners_cash_out_their_share_of_payouts_from_the_earnings_page(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'name' => 'Ana Cruz']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        $riders = Schema::hasColumn('logistics_payouts', 'rider_commissions');
        LogisticsPayout::create(['logistics_provider_id' => $partner->id, 'parcels' => 5, 'shipping_fees' => 750, 'amount' => 600, 'partner_share' => 0.8, 'paid_at' => now()]
            + ($riders ? ['rider_commissions' => 75] : []));
        $share = $riders ? 525 : 600; // riders' commissions are theirs, sent through rider withdrawals
        $this->actingAs($owner);

        $this->get(route('logistics.earnings'))->assertOk()
            ->assertSee('Wallet')->assertSee('Demo GCash: no real money is sent')
            ->assertSee("Your company's share of each payout ZAYLO records (after your riders' commissions)")
            ->assertSee('&#8369;'.number_format($share, 2), false)
            ->assertSee(route('logistics.wallet.cash-out'), false);

        $this->post(route('logistics.wallet.cash-out'), ['amount' => $share + 1, 'gcash_number' => '09181234567', 'gcash_name' => 'Ana Cruz'])
            ->assertSessionHasErrors(['amount' => 'You can cash out up to ₱'.number_format($share, 2).', your wallet balance.']);
        $this->post(route('logistics.wallet.cash-out'), ['amount' => 50, 'gcash_number' => '09181234567', 'gcash_name' => 'Ana Cruz'])
            ->assertSessionHasErrors(['amount' => 'The smallest cash-out is ₱100.']);
        $this->post(route('logistics.wallet.cash-out'), ['amount' => 100, 'gcash_number' => '9181234567', 'gcash_name' => 'Ana Cruz'])
            ->assertSessionHasErrors('gcash_number');

        $this->post(route('logistics.wallet.cash-out'), ['amount' => 300, 'gcash_number' => '09181234567', 'gcash_name' => 'Ana Cruz'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $status) => str_starts_with($status, '₱300.00 was sent to GCash 0918 ••• 4567 (demo transfer, reference GCASH-DEMO-'));
        $this->assertDatabaseHas('wallet_cashouts', ['user_id' => $owner->id, 'amount' => 300, 'gcash_number' => '09181234567']);

        $this->get(route('logistics.earnings'))->assertOk()
            ->assertSee('&#8369;'.number_format($share - 300, 2), false)
            ->assertSee('&#8369;300.00 to GCash 0918 ••• 4567', false);

        // Sellers have no partner wallet; riders use their own (no rider cash-out route here).
        $this->actingAs(User::factory()->create(['role' => 'seller', 'status' => 'active']))
            ->post(route('logistics.wallet.cash-out'), ['amount' => 100, 'gcash_number' => '09181234567', 'gcash_name' => 'X'])->assertForbidden();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('courier.wallet.cash-out'));
    }
}
