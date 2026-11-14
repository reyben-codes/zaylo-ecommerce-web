<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Services\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class ExpiredSellerVoucherPruningTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_seller_vouchers_expired_for_at_least_24_hours_are_deleted(): void
    {
        $this->travelTo(now()->startOfSecond());
        $seller = User::factory()->create(['role' => 'seller']);
        $shopId = $seller->sellerShop()->id;
        $base = ['seller_id' => $shopId, 'created_by' => $seller->id, 'type' => 'fixed', 'value' => 20, 'funded_by' => 'seller'];
        $expired = Voucher::create($base + ['code' => 'OLD', 'expires_at' => now()->subHours(25)]);
        $boundary = Voucher::create($base + ['code' => 'BOUNDARY', 'expires_at' => now()->subHours(24), 'is_active' => false]);
        $retained = [
            Voucher::create($base + ['code' => 'RECENT', 'expires_at' => now()->subHours(24)->addSecond()]),
            Voucher::create($base + ['code' => 'FUTURE', 'expires_at' => now()->addDay()]),
            Voucher::create($base + ['code' => 'NO-EXPIRY']),
            Voucher::create(['code' => 'ADMIN-SHOP', 'type' => 'fixed', 'value' => 20, 'funded_by' => 'seller', 'seller_id' => $shopId,
                'created_by' => User::factory()->create(['role' => 'admin'])->id, 'expires_at' => now()->subDays(2)]),
            Voucher::create(['code' => 'PLATFORM', 'type' => 'free_shipping', 'expires_at' => now()->subDays(2)]),
        ];

        $this->artisan('model:prune', ['--model' => [Voucher::class]])->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelMissing($boundary);
        foreach ($retained as $voucher) {
            $this->assertModelExists($voucher);
        }
        // Re-running the cleanup is harmless.
        $this->artisan('model:prune', ['--model' => [Voucher::class]])->assertSuccessful();
        $this->assertDatabaseCount('vouchers', count($retained));
    }

    public function test_used_expired_vouchers_are_deleted_without_changing_order_history(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $buyer = User::factory()->create(['role' => 'buyer']);
        $voucher = app(Vouchers::class)->createForSeller([
            'code' => 'USED-EXPIRED', 'type' => 'fixed', 'value' => 20,
            'expires_at' => now()->subHours(25), 'used_count' => 1,
        ], $seller->sellerShop()->id, $seller);
        VoucherClaim::create(['voucher_id' => $voucher->id, 'user_id' => $buyer->id, 'claimed_at' => now()->subDays(2), 'used_at' => now()->subDays(2)]);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'reference' => 'EXPIRED-VOUCHER-ORDER', 'payment_method' => 'cod',
            'total_minor' => 8000, 'shipping_address' => [], 'voucher_code' => $voucher->code, 'voucher_discount' => 20,
        ]);

        $this->artisan('model:prune', ['--model' => [Voucher::class]])->assertSuccessful();

        $this->assertModelMissing($voucher);
        $this->assertDatabaseMissing('voucher_claims', ['voucher_id' => $voucher->id]);
        $this->assertDatabaseMissing('voucher_sellers', ['voucher_id' => $voucher->id]);
        $this->assertSame('USED-EXPIRED', $order->fresh()->voucher_code);
        $this->assertEquals(20, $order->fresh()->voucher_discount);
        $this->assertSame(8000, (int) $order->fresh()->total_minor);
    }

    public function test_voucher_cleanup_is_scheduled_every_minute_without_overlapping(): void
    {
        $event = collect(Schedule::events())->first(fn ($event) => str_contains($event->command ?? '', 'model:prune')
            && str_contains($event->command, Voucher::class));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
