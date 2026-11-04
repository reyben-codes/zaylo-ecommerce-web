<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LogisticsPayout;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\LogisticsPayouts;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logistics partners earn 80% of the shipping fee of each parcel they deliver; ZAYLO keeps 20% and pays partners
 * in batches, never paying a parcel twice.
 */
class LogisticsPayoutTest extends TestCase
{
    use RefreshDatabase;

    private function partner(string $name): array
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);

        return [$owner, LogisticsProvider::create(['user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved'])];
    }

    private function parcel(string $tracking, LogisticsProvider $provider, string $stage = 'delivered', int $feeMinor = 12000): Shipment
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active'])->sellers()->create(['name' => 'Shop '.$tracking, 'slug' => 'shop-'.$tracking, 'status' => 'approved']);
        $order = Order::create([
            'buyer_id' => User::factory()->create(['role' => 'buyer'])->id, 'reference' => 'ZAY-'.$tracking, 'total_minor' => 10000,
            'payment_method' => 'cod', 'shipping_address' => ['recipient' => 'Juan Dela Cruz', 'city' => 'Pasig City'],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 10000, 'shipping_fee_minor' => $feeMinor, 'commission_minor' => 0, 'status' => 'shipped',
        ]);

        return Shipment::create([
            'seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'tracking_code' => $tracking,
            'status' => ShipmentWorkflow::stored($stage),
        ]);
    }

    public function test_partners_earn_80_percent_of_delivered_shipping_fees_and_are_paid_once(): void
    {
        [$owner, $express] = $this->partner('Express Delivery');
        [, $swift] = $this->partner('Swift Couriers');
        $this->parcel('ZX-1', $express);
        $this->parcel('ZX-2', $express);
        $this->parcel('ZX-3', $express, 'out_for_delivery'); // not delivered yet: not earned
        $this->parcel('SW-1', $swift);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $payouts = app(LogisticsPayouts::class);
        $summary = $payouts->summary($payouts->rows());
        $this->assertSame(['parcels' => 3, 'fees' => 360.0, 'partner' => 288.0, 'zaylo' => 72.0, 'paid' => 0.0, 'owed' => 288.0, 'unpaidParcels' => 3], $summary);

        $this->actingAs($admin)->get(route('admin.logistics-payouts'))->assertOk()
            ->assertSee('Express Delivery')->assertSee('₱192.00')->assertSee('Mark as paid');

        $this->post(route('admin.logistics-payouts.pay', $express), ['reference' => 'GCASH-123'])->assertRedirect()
            ->assertSessionHas('status', 'Recorded a payout of ₱192.00 to Express Delivery for 2 delivered parcels.');
        $payout = LogisticsPayout::sole();
        $this->assertSame([2, 240.0, 192.0, 'GCASH-123', $admin->id], [$payout->parcels, $payout->shipping_fees, $payout->amount, $payout->reference, $payout->paid_by]);
        $this->assertTrue(ActivityLog::where('event', 'admin.logistics-payouts.pay')->exists());

        // Nothing is left to pay Express, and Swift is untouched.
        $this->post(route('admin.logistics-payouts.pay', $express))->assertSessionHas('status', 'Express Delivery has no unpaid delivered parcels.');
        $this->assertSame(1, LogisticsPayout::count());
        $this->assertSame(['paid' => 192.0, 'owed' => 96.0], array_intersect_key($payouts->summary($payouts->rows()), ['paid' => 0, 'owed' => 0]));

        // The partner sees its own earnings and payouts.
        $this->actingAs($owner)->get(route('logistics.earnings'))->assertOk()
            ->assertSee('GCASH-123')->assertSee('ZX-1')->assertDontSee('SW-1')->assertSee('&#8369;192.00', false);
    }

    public function test_only_admins_record_payouts(): void
    {
        [$owner, $express] = $this->partner('Express Delivery');
        $this->parcel('ZX-1', $express);

        $this->actingAs($owner)->post(route('admin.logistics-payouts.pay', $express))->assertForbidden();
        $this->assertSame(0, LogisticsPayout::count());
    }

    public function test_free_shipping_orders_still_pay_the_partner_the_full_fee_share(): void
    {
        // A voucher stores the order's shipping fee as 0 plus a shipping discount; the partner's share counts the full fee.
        $shipment = new Shipment;
        $shipment->setRelation('sellerOrder', null);
        $voucherOrder = (new Order)->forceFill(['shipping_fee' => 0]);
        $voucherOrder->forceFill(Order::usesLegacySchema() ? ['shipping_discount' => 120] : ['shipping_discount_minor' => 12000]);
        $shipment->setRelation('order', $voucherOrder);

        $this->assertSame(120.0, LogisticsPayouts::feeOf($shipment));
    }
}
