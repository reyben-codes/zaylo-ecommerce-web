<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderProximity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class RiderSellerPickupTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    public function test_rider_scans_seller_pickup_only_when_local_on_shift_and_with_the_same_partner(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Hub', 'slug' => 'pickup-hub', 'status' => 'approved']);
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $rider = Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'status' => 'approved', 'is_active' => true]);
        $address = $user->addresses()->create(['recipient' => 'Rider', 'phone' => '09171234567', 'barangay' => 'Central', 'line1' => 'Home', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $seller->addresses()->create(['recipient' => 'Seller', 'phone' => '09171234567', 'barangay' => 'Central', 'line1' => 'Shop', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
        $shop = $seller->sellerShop();
        $order = Order::create(['buyer_id' => User::factory()->create()->id, 'reference' => 'PICKUP-ORDER', 'total_minor' => 10000, 'payment_method' => 'cod', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $shop->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'ready_to_ship']);
        $product = \App\Models\Product::create(['seller_id' => $seller->id, 'name' => 'Packed blender', 'category' => 'blenders', 'price' => 100, 'stock' => 2, 'is_active' => true]);
        $sellerOrder->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->variants()->firstOrFail()->id, 'product_name' => 'Packed blender', 'variant_name' => 'Standard', 'unit_price_minor' => 10000, 'quantity' => 1]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'tracking_code' => 'PICKUP-CODE', 'status' => \App\Services\ShipmentWorkflow::stored('ready'), 'pickup_confirmed_at' => now()]);
        $this->signInMobile($user);
        $scan = fn ($code = 'PICKUP-CODE') => $this->postJson('/api/mobile/rider/seller-pickups/scan', ['code' => $code]);
        $scan()->assertUnprocessable();
        $shift = $rider->attendances()->create(['logistics_provider_id' => $provider->id, 'work_date' => now('Asia/Manila')->toDateString(), 'time_in_at' => now()]);
        $scan()->assertUnprocessable(); // Logistics must assign the pickup first.
        $this->assertSame('Manila, Metro Manila', \App\Services\RiderSellerPickups::pickupArea($shipment->fresh()));
        $this->withoutToken()->actingAs($owner, 'web')->post(route('logistics.parcels.pickup-rider', $shipment), ['rider_id' => $rider->id])
            ->assertSessionHasErrors('rider_id'); // Last Mile riders cannot receive seller pickups.
        $this->patch(route('logistics.riders.type', $rider), ['rider_type' => 'pickup'])->assertSessionHasNoErrors();
        $this->withoutToken()->actingAs($owner, 'web')->get(route('logistics.pickups', ['tab' => 'scheduled']))
            ->assertOk()->assertViewHas('pickupRiders', fn ($rows) => $rows->count() === 1)->assertViewHas('shipments', fn ($rows) => $rows->count() === 1)->assertSee('Assign pickup rider')->assertSee($user->name);
        $this->post(route('logistics.parcels.pickup-rider', $shipment), ['rider_id' => $rider->id])->assertSessionHasNoErrors();
        $this->signInMobile($user);
        $this->getJson('/api/mobile/rider/dashboard')->assertOk()->assertJsonPath('seller_pickups.0.tracking_number', 'PICKUP-CODE')
            ->assertJsonPath('seller_pickups.0.items.0.name', 'Packed blender')->assertJsonPath('seller_pickups.0.status', 'confirmed');
        $scan('WRONG')->assertUnprocessable();
        $address->update(['city' => 'Cebu']);
        $scan()->assertUnprocessable();
        $address->update(['city' => 'Manila']);
        $shipment->update(['pickup_courier_id' => $owner->id]);
        $scan()->assertUnprocessable();
        $otherProvider = LogisticsProvider::create(['user_id' => User::factory()->create(['role' => 'logistics'])->id, 'name' => 'Other hub', 'slug' => 'other-hub', 'status' => 'approved']);
        $shipment->update(['pickup_courier_id' => null, 'logistics_provider_id' => $otherProvider->id]);
        $scan()->assertUnprocessable();
        $shipment->update(['logistics_provider_id' => $provider->id, 'pickup_courier_id' => $user->id]);
        $shipment->update(['pickup_confirmed_at' => null]);
        $scan()->assertUnprocessable();
        $shipment->update(['pickup_confirmed_at' => now()]);
        $scan(' pickup-code ')->assertOk()->assertJsonPath('pickup.status', 'picked_up');
        $this->assertSame($user->id, $shipment->fresh()->pickup_courier_id);
        $this->assertSame('shipped', $sellerOrder->fresh()->status);
        $this->assertNotNull($shipment->fresh()->pickup_scanned_at);
        $this->getJson('/api/mobile/rider/dashboard')->assertOk()->assertJsonPath('seller_pickups.0.status', 'picked_up');
        $scan()->assertUnprocessable();
        $this->withoutToken()->actingAs($owner, 'web')->get(route('logistics.incoming'))->assertOk()
            ->assertSee('PICKUP-CODE')->assertSee($user->name)->assertSee('Packed blender')->assertSee('Parcel physically received');
        $receive = ['status' => 'in_transit', 'parcel_received' => '1', 'tracking_verified' => '1', 'items_checked' => '1'];
        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'in_transit'])->assertSessionHasErrors('parcel_received');
        $this->assertNull($shipment->fresh()->hub_received_at);
        $this->actingAs($otherProvider->owner, 'web')->patch(route('logistics.parcels.advance', $shipment), $receive)->assertForbidden();
        $this->actingAs($owner, 'web')->patch(route('logistics.parcels.advance', $shipment), $receive)->assertSessionHasNoErrors();
        $this->assertSame($owner->id, $shipment->fresh()->hub_received_by);
        $this->assertTrue($shipment->fresh()->hub_receipt_checks['items_checked']);
        $this->patch(route('logistics.parcels.advance', $shipment), $receive)->assertSessionHasErrors('status');
        $this->post(route('logistics.parcels.pickup-rider', $shipment), ['rider_id' => $rider->id])->assertSessionHasErrors('shipment');
        $this->signInMobile($user);
        $this->getJson('/api/mobile/rider/dashboard')->assertOk()->assertJsonPath('seller_pickups.0.status', 'arrived_at_hub');
        $deliveryUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $deliveryRider = Rider::create(['user_id' => $deliveryUser->id, 'logistics_provider_id' => $provider->id, 'status' => 'approved', 'is_active' => true]);
        $deliveryUser->addresses()->create(['recipient' => 'Delivery rider', 'phone' => '09171234567', 'barangay' => 'Central', 'line1' => 'Home', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
        $deliveryRider->attendances()->create(['logistics_provider_id' => $provider->id, 'work_date' => now('Asia/Manila')->toDateString(), 'time_in_at' => now()]);
        $this->withoutToken()->actingAs($owner, 'web')->patch(route('logistics.parcels.sort', $shipment), ['area' => 'Manila, Metro Manila'])->assertSessionHasNoErrors();
        $this->post(route('logistics.parcels.assign', $shipment), ['rider_id' => $rider->id])->assertSessionHasErrors('rider_id');
        $this->post(route('logistics.parcels.assign', $shipment), ['rider_id' => $deliveryRider->id])->assertSessionHasNoErrors();
        $this->assertSame($user->id, $shipment->fresh()->pickup_courier_id);
        $this->assertSame($deliveryRider->id, $shipment->fresh()->rider_id);
        $this->signInMobile($deliveryUser);
        $this->getJson('/api/mobile/rider/dashboard')->assertOk()->assertJsonCount(0, 'seller_pickups')->assertJsonPath('active.0.id', $shipment->id);
        $this->signInMobile($user);
        $shift->update(['time_out_at' => now()]);
        $scan()->assertUnprocessable();
    }

    public function test_assignment_options_hide_wrong_city_unknown_and_inactive_riders(): void
    {
        $provider = LogisticsProvider::create(['user_id' => User::factory()->create(['role' => 'logistics'])->id, 'name' => 'Hub', 'slug' => 'local-hub', 'status' => 'approved']);
        $riders = collect();
        foreach (['Manila', 'Cebu', null] as $city) {
            $user = User::factory()->create(['role' => 'rider', 'status' => 'active']);
            $rider = Rider::create(['logistics_provider_id' => $provider->id, 'user_id' => $user->id, 'status' => 'approved', 'is_active' => true]);
            if ($city) {
                $user->addresses()->create(['recipient' => 'Rider', 'phone' => '09171234567', 'barangay' => 'Central', 'line1' => 'Home', 'city' => $city, 'province' => 'Metro Manila', 'is_default' => true]);
            }
            $riders->push($rider);
        }
        $this->assertSame([$riders[0]->id], app(RiderProximity::class)->serving($riders, 'City of Manila, Metro Manila')->pluck('id')->all());
        $riders[0]->update(['is_active' => false]);
        $this->assertTrue(app(RiderProximity::class)->serving($riders, 'Manila, Metro Manila')->isEmpty());
    }
}
