<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\User;
use App\Support\Waybill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsPartnerWaybillTest extends TestCase
{
    use RefreshDatabase;

    private function partner(string $name, ?string $layout, string $status = 'approved'): LogisticsProvider
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)), 'status' => $status]);
        $partner->forceFill(['waybill_layout' => $layout])->save();
        return $partner;
    }

    private function parcel(User $seller): Order
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'ZAY-'.uniqid(), 'total_minor' => 112000,
            'payment_method' => 'cod', 'shipping_address' => ['address' => 'Calamba, Laguna', 'recipient' => 'Buyer']]);
        $order->sellerOrders()->create(['seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 100000,
            'shipping_fee_minor' => 12000, 'commission_minor' => 10000, 'status' => 'packed']);
        return $order;
    }

    public function test_seller_can_select_each_approved_partner_and_print_its_design(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $first = $this->partner('North Parcel', 'jnt_express');
        $second = $this->partner('South Parcel', 'flash_express');
        $pending = $this->partner('Pending Parcel', null, 'pending');
        $inactive = $this->partner('Inactive Parcel', null);
        $inactive->owner->update(['status' => 'suspended']);
        $this->actingAs($seller)->get(route('seller.pickups'))->assertOk()
            ->assertViewHas('partners', fn ($partners) => $partners->pluck('id')->sort()->values()->all() === [$first->id, $second->id])
            ->assertViewHas('suggestedPartner', null);
        foreach ([$first, $second] as $partner) {
            $order = $this->parcel($seller);
            $this->actingAs($seller)->post(route('seller.pickups.schedule'), ['order_id' => $order->id,
                'logistics_provider_id' => $partner->id, 'pickup_date' => now()->addDay()->toDateString()])
                ->assertSessionHasNoErrors();
            $shipment = $order->fresh()->deliveryShipment();
            $this->assertSame($partner->id, $shipment->logistics_provider_id);
            $waybill = Waybill::for($shipment);
            $this->assertSame($partner->waybill_layout, $waybill['courier']);
            $this->assertSame($partner->name, $waybill['carrier']);
            $this->get(route('seller.orders.waybill.print', $order))->assertOk()
                ->assertSee('wb-courier-'.str_replace('_', '-', $partner->waybill_layout), false)
                ->assertSee($partner->name)->assertDontSee('images/couriers/jnt-express.png', false)
                ->assertDontSee('images/couriers/flash-express.png', false);
            $this->actingAs($partner->owner)->get(route('logistics.parcels.waybill.print', $shipment))->assertOk()
                ->assertSee('wb-courier-'.str_replace('_', '-', $partner->waybill_layout), false)->assertSee($partner->name);
        }
        $order = $this->parcel($seller);
        $this->actingAs($seller)->post(route('seller.pickups.schedule'), ['order_id' => $order->id,
            'logistics_provider_id' => $pending->id])->assertSessionHasErrors('logistics_provider_id');
        $this->assertNull($order->fresh()->deliveryShipment());
    }

    public function test_partner_brand_and_saved_design_follow_the_parcel_through_delivery(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $partner = $this->partner('Express Delivery', null);
        $order = $this->parcel($seller);
        $shipment = $order->sellerOrders->first()->shipment()->create(['logistics_provider_id' => $partner->id,
            'tracking_code' => 'PARTNER-TRACK', 'status' => 'unassigned']);
        $this->assertSame('zaylo_express', Waybill::layoutFor($partner));
        $this->actingAs($partner->owner)->patch(route('logistics.account.waybill'), ['waybill_layout' => 'flash_express'])
            ->assertSessionHasNoErrors();
        $shipment->update(['status' => 'out_for_delivery']);
        $this->actingAs($seller)->get(route('seller.orders.waybill.print', $order))->assertOk()
            ->assertSee('wb-courier-flash-express', false)->assertSee('Express Delivery')
            ->assertDontSee('images/couriers/flash-express.png', false);
    }

    public function test_multi_seller_order_prints_only_the_requesting_sellers_parcel(): void
    {
        $firstSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $secondSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $firstPartner = $this->partner('First Partner', 'jnt_express');
        $secondPartner = $this->partner('Second Partner', 'flash_express');
        $order = $this->parcel($firstSeller);
        $order->sellerOrders->first()->shipment()->create(['logistics_provider_id' => $firstPartner->id,
            'tracking_code' => 'FIRST-SELLER-PARCEL', 'status' => 'unassigned']);
        $secondOrder = $order->sellerOrders()->create(['seller_id' => $secondSeller->sellerShop()->id,
            'subtotal_minor' => 100000, 'shipping_fee_minor' => 12000, 'commission_minor' => 10000, 'status' => 'packed']);
        $secondOrder->shipment()->create(['logistics_provider_id' => $secondPartner->id,
            'tracking_code' => 'SECOND-SELLER-PARCEL', 'status' => 'unassigned']);
        $this->actingAs($secondSeller)->get(route('seller.orders.waybill.print', $order))->assertOk()
            ->assertSee('SECOND-SELLER-PARCEL')->assertSee('Second Partner')->assertDontSee('FIRST-SELLER-PARCEL');
        $outsider = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($outsider)->get(route('seller.orders.waybill.print', $order))->assertNotFound();
    }
}
