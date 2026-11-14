<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class LogisticsRiderTypeTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    private function provider(string $slug = 'hub'): LogisticsProvider
    {
        return LogisticsProvider::create([
            'user_id' => User::factory()->create(['role' => 'logistics', 'status' => 'active'])->id,
            'name' => $slug, 'slug' => $slug, 'status' => 'approved',
        ]);
    }

    private function rider(LogisticsProvider $provider): Rider
    {
        return Rider::create([
            'user_id' => User::factory()->create(['role' => 'rider', 'status' => 'active'])->id,
            'logistics_provider_id' => $provider->id, 'status' => 'approved', 'is_active' => true,
            'rider_type' => 'delivery',
        ]);
    }

    public function test_logistics_sets_both_types_and_rider_stays_manageable_on_the_riders_page(): void
    {
        $provider = $this->provider();
        $rider = $this->rider($provider);
        foreach (['pickup', 'delivery'] as $type) {
            $this->actingAs($provider->owner)->patch(route('logistics.riders.type', $rider), ['rider_type' => $type])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($type, $rider->fresh()->rider_type);
            $this->assertSame($type === 'pickup', $rider->fresh()->isPickupRider());
            $this->assertSame($type === 'delivery', $rider->fresh()->isDeliveryRider());
            $this->assertSame($type === 'pickup', $provider->couriers()->whereKey($rider->id)->exists());
            $this->assertSame($type === 'delivery', $provider->deliveryRiders()->whereKey($rider->id)->exists());
            $this->get(route('logistics.riders'))->assertOk()
                ->assertViewHas('status', 'approved')
                ->assertSee($rider->user->name)->assertSee('Save assignment type')
                ->assertSee('Riders (First &amp; Last Mile)', false);
            if ($type === 'pickup') {
                $this->get(route('logistics.couriers'))->assertOk()
                    ->assertSee('Pickup fleet (First Mile)')
                    ->assertSee(route('logistics.riders.type', $rider))
                    ->assertSee('Save assignment type');
            }
        }
    }

    public function test_type_changes_validate_input_and_belong_to_the_logistics_company(): void
    {
        $provider = $this->provider();
        $rider = $this->rider($provider);
        $other = $this->provider('other');
        $this->actingAs($other->owner)->patch(route('logistics.riders.type', $rider), ['rider_type' => 'pickup'])->assertForbidden();
        $this->actingAs($provider->owner)->patch(route('logistics.riders.type', $rider), ['rider_type' => 'both'])
            ->assertSessionHasErrorsIn('riderType'.$rider->id, 'rider_type');
        $this->assertSame('delivery', $rider->fresh()->rider_type);
    }

    public function test_type_change_is_blocked_until_assigned_parcels_are_finished(): void
    {
        $provider = $this->provider();
        $rider = $this->rider($provider);
        $seller = User::factory()->create(['role' => 'seller']);
        $order = Order::create(['buyer_id' => User::factory()->create()->id, 'reference' => 'TYPE-ORDER', 'total_minor' => 10000, 'payment_method' => 'cod', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'ready_to_ship']);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'tracking_code' => 'TYPE-CODE', 'status' => 'assigned', 'rider_id' => $rider->id]);
        $this->actingAs($provider->owner)->patch(route('logistics.riders.type', $rider), ['rider_type' => 'pickup'])
            ->assertSessionHasErrorsIn('riderType'.$rider->id, 'rider_type');
        $shipment->update(['status' => 'delivered']);
        $this->patch(route('logistics.riders.type', $rider), ['rider_type' => 'pickup'])->assertSessionHasNoErrors();
        $shipment->update(['status' => 'picked_up', 'pickup_courier_id' => $rider->user_id, 'rider_id' => null]);
        $this->patch(route('logistics.riders.type', $rider), ['rider_type' => 'delivery'])
            ->assertSessionHasErrorsIn('riderType'.$rider->id, 'rider_type');
        $shipment->update(['hub_received_at' => now()]);
        $this->patch(route('logistics.riders.type', $rider), ['rider_type' => 'delivery'])->assertSessionHasNoErrors();
    }

    public function test_mobile_access_follows_the_type_set_by_logistics(): void
    {
        $provider = $this->provider();
        $rider = $this->rider($provider);
        $this->actingAs($provider->owner)->patch(route('logistics.riders.type', $rider), ['rider_type' => 'pickup'])->assertSessionHasNoErrors();
        $this->signInMobile($rider->user);
        $this->getJson('/api/mobile/rider/pickup/queue')->assertOk();
        $this->getJson('/api/mobile/rider/delivery/manifest')->assertForbidden();
    }
}
