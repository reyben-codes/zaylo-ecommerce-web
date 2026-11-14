<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderProximity;
use App\Services\RiderSellerPickups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class RiderPickupAddressMatchingTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    public static function addresses(): array
    {
        return [
            'same PSGC city with different display names' => [
                ['city' => 'Manila', 'province' => 'Metro Manila', 'city_municipality_code' => '133901000'],
                ['city' => 'City of Manila (Capital)', 'province' => '', 'region_name' => 'National Capital Region (NCR)', 'city_municipality_code' => '133901000'], true,
            ],
            'legacy NCR and city aliases' => [
                ['city' => 'City of Manila (Capital)', 'province' => 'NCR'],
                ['city' => 'Manila', 'province' => '', 'region_name' => 'National Capital Region (NCR)'], true,
            ],
            'legacy whitespace accents and city suffix' => [
                ['city' => '  Dasmariñas   City ', 'province' => ' Province of Cavite '],
                ['city' => 'City of Dasmarinas', 'province' => 'Cavite'], true,
            ],
            'different codes with identical display names' => [
                ['city' => 'San Fernando', 'province' => 'Pampanga', 'city_municipality_code' => '035416000'],
                ['city' => 'San Fernando', 'province' => 'Pampanga', 'city_municipality_code' => '013314000'], false,
            ],
            'same name in another province without codes' => [
                ['city' => 'San Fernando', 'province' => 'Pampanga'],
                ['city' => 'San Fernando', 'province' => 'La Union'], false,
            ],
            'unrelated legacy city remains excluded' => [
                ['city' => 'Cebu', 'province' => 'Cebu'],
                ['city' => 'Manila', 'province' => 'Metro Manila'], false,
            ],
            'rider lives at the hub city while the assigned seller is in another city' => [
                ['city' => 'Manila', 'province' => 'Metro Manila', 'city_municipality_code' => '133901000'],
                ['city' => 'City of Bacoor', 'province' => 'Cavite', 'city_municipality_code' => '042103000'], false,
            ],
        ];
    }

    #[DataProvider('addresses')]
    public function test_area_checks_apply_to_assignment_but_do_not_block_an_existing_pickup_assignment(array $home, array $pickup, bool $matches): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Pickup Hub', 'slug' => 'pickup-hub', 'status' => 'approved',
            'city_name' => $home['city'], 'city_code' => $home['city_municipality_code'] ?? null, 'province_name' => $home['province']]);
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $rider = Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id,
            'rider_type' => 'pickup', 'status' => 'approved', 'is_active' => true]);
        $baseAddress = ['recipient' => 'Contact', 'phone' => '09171234567', 'line1' => '123 Main Street', 'barangay' => 'Central', 'is_default' => true];
        $user->addresses()->create($home + $baseAddress);
        $rider->attendances()->create(['logistics_provider_id' => $provider->id, 'work_date' => now('Asia/Manila')->toDateString(), 'time_in_at' => now()]);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        // The shop's explicit pickup address takes precedence over the seller's separate default home address.
        $seller->addresses()->create(['city' => 'Davao', 'province' => 'Davao del Sur'] + $baseAddress);
        $pickupAddress = $seller->addresses()->create($pickup + ['is_default' => false] + $baseAddress);
        $shop = $seller->sellerShop();
        $shop->update(['pickup_address_id' => $pickupAddress->id]);
        $order = Order::create(['buyer_id' => User::factory()->create()->id, 'reference' => 'PICKUP-MATCH',
            'total_minor' => 100000, 'payment_method' => 'cod', 'shipping_address' => ['city' => 'Elsewhere']]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $shop->id,
            'subtotal_minor' => 100000, 'shipping_fee_minor' => 12000, 'commission_minor' => 10000, 'status' => 'ready_to_ship']);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id,
            'tracking_code' => 'PICKUP-MATCH-CODE', 'status' => \App\Services\ShipmentWorkflow::stored('ready'), 'pickup_confirmed_at' => now(),
            'pickup_courier_id' => $user->id]);

        $area = RiderSellerPickups::pickupArea($shipment);
        $this->assertSame($pickupAddress->id, RiderSellerPickups::pickupAddress($shipment)->id);
        $this->assertSame($matches ? [$rider->id] : [], app(RiderProximity::class)
            ->serving(collect([$rider]), $area, $pickupAddress)->pluck('id')->all());
        $this->actingAs($owner)->get(route('logistics.pickups', ['tab' => 'scheduled']))->assertOk()
            ->assertSee($matches ? 'Assign pickup rider' : 'No on-shift pickup riders', false);
        $assigned = $this->post(route('logistics.parcels.pickup-rider', $shipment), ['rider_id' => $rider->id]);
        $matches ? $assigned->assertSessionHasNoErrors() : $assigned->assertSessionHasErrors('rider_id');

        // This parcel already belongs to this rider, even when a later assignment check rejects the current address.
        $this->assertSame($user->id, $shipment->fresh()->pickup_courier_id);
        $scan = $this->signInMobile($user)->postJson('/api/mobile/rider/pickup/scan', ['code' => 'PICKUP-MATCH-CODE']);
        $scan->assertOk();
        $this->assertNotNull($shipment->fresh()->pickup_scanned_at);
    }
}
