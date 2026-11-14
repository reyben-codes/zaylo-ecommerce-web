<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerLogisticsSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_seller_selects_the_partner_for_their_own_parcel(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'MULTI-SELLER-DELIVERY', 'total_minor' => 224000,
            'payment_method' => 'cod', 'shipping_address' => []]);
        $rows = collect();
        foreach (['One', 'Two'] as $name) {
            $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
            $partner = LogisticsProvider::create([
                'user_id' => User::factory()->create(['role' => 'logistics', 'status' => 'active'])->id,
                'name' => 'Partner '.$name, 'slug' => 'partner-'.strtolower($name), 'status' => 'approved', 'rider_rate_minor' => 6000,
            ]);
            $sellerOrder = SellerOrder::create([
                'order_id' => $order->id, 'seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 100000,
                'shipping_fee_minor' => 12000, 'commission_minor' => 10000, 'status' => 'packed',
                'settlement_breakdown' => Settlement::deliveryBreakdown(1000, 120) + ['logistics_selection' => 'seller'],
            ]);
            $rows->push([$seller, $sellerOrder, $partner]);
        }
        foreach ($rows as [$seller, $sellerOrder, $partner]) {
            $this->actingAs($seller)->post(route('seller.pickups.schedule'), [
                'order_id' => $order->id, 'logistics_provider_id' => $partner->id,
            ])->assertRedirect()->assertSessionHasNoErrors();
            $sellerOrder->refresh();
            $this->assertEquals($partner->id, $sellerOrder->logistics_provider_id);
            $this->assertEquals($partner->id, $sellerOrder->shipment->logistics_provider_id);
            $this->assertEquals(60, $sellerOrder->settlement_breakdown['rider_earnings']);
            $this->assertEquals(224000, $order->fresh()->total_minor);
        }
        $this->assertDatabaseCount('shipments', 2);
        $this->assertSame('Partner One, Partner Two', $order->fresh()->courierName());
        [$seller, $sellerOrder, $partner] = $rows->first();
        $this->actingAs($seller)->post(route('seller.pickups.schedule'), [
            'order_id' => $order->id, 'logistics_provider_id' => $rows->last()[2]->id,
        ])->assertSessionHasErrors('order_id');
        $this->assertEquals($partner->id, $sellerOrder->fresh()->shipment->logistics_provider_id);
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($other)->post(route('seller.pickups.schedule'), [
            'order_id' => $order->id, 'logistics_provider_id' => $partner->id,
        ])->assertForbidden();
    }
}
