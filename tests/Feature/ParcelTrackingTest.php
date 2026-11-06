<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyOrderTables;
use Tests\Support\PsgcFixtures;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Buyers pin the exact spot of an address; the order keeps the pin, the rider's app shows it, and while the parcel
 * is out for delivery the buyer follows the rider's reported position on a map.
 */
class ParcelTrackingTest extends TestCase
{
    use LegacyOrderTables;
    use PsgcFixtures;
    use RefreshDatabase;
    use SignsInMobile;

    private User $buyer;

    private User $rider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
        // The deployed tables get the map columns from the migration.
        (require database_path('migrations/2026_11_07_000004_add_map_locations.php'))->up();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        $this->rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        Rider::create(['user_id' => $this->rider->id, 'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);
    }

    /** An order pinned at a Calamba door, its parcel with the rider at [$status]. */
    private function parcel(string $status = 'out_for_delivery'): array
    {
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-TRACK-'.fake()->unique()->numberBetween(100, 999), 'user_id' => $this->buyer->id, 'status' => 'in_transit',
            'subtotal' => 500, 'shipping_fee' => 60, 'total' => 560, 'payment_method' => 'cod', 'recipient_name' => 'Maria Santos',
            'phone' => '09170000000', 'shipping_address' => '12 Rizal St, Calamba, Laguna', 'delivery_latitude' => 14.2116, 'delivery_longitude' => 121.1653,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $shipmentId = DB::table('shipments')->insertGetId([
            'order_id' => $orderId, 'courier_id' => $this->rider->id, 'tracking_number' => 'TRK-'.$orderId, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$orderId, $shipmentId];
    }

    public function test_buyers_pin_the_exact_spot_of_an_address(): void
    {
        $this->fakeLocations();
        $this->signInMobile($this->buyer);

        $this->postJson('/api/mobile/addresses', $this->addressInput(['latitude' => 14.4296, 'longitude' => 120.9367]))->assertCreated()
            ->assertJsonPath('address.pin', ['latitude' => 14.4296, 'longitude' => 120.9367]);
        $this->postJson('/api/mobile/addresses', $this->addressInput(['latitude' => 35.68, 'longitude' => 139.76]))->assertUnprocessable()
            ->assertJsonPath('errors.latitude.0', 'Pin a spot in the Philippines.');
        $this->postJson('/api/mobile/addresses', $this->addressInput(['latitude' => 14.4296]))->assertUnprocessable()->assertJsonValidationErrors('longitude');

        // Older apps send no pin: it is kept, unless the address moves to another barangay.
        $address = $this->buyer->addresses()->sole();
        $this->putJson("/api/mobile/addresses/{$address->id}", $this->addressInput(['line1' => '2 Test Street']))->assertOk()
            ->assertJsonPath('address.pin.latitude', 14.4296);
        $this->putJson("/api/mobile/addresses/{$address->id}", $this->addressInput(['city_municipality_code' => '137404000', 'barangay_code' => '137404001', 'region_code' => '130000000', 'province_code' => null]))
            ->assertOk()->assertJsonPath('address.pin', null);
    }

    public function test_the_buyer_follows_the_rider_while_the_parcel_is_out_for_delivery(): void
    {
        [$orderId, $shipmentId] = $this->parcel();

        // The rider sees the buyer's pin.
        $this->signInMobile($this->rider)->getJson("/api/mobile/rider/deliveries/$shipmentId")->assertOk()
            ->assertJsonPath('delivery.destination', ['latitude' => 14.2116, 'longitude' => 121.1653]);
        $this->getJson('/api/mobile/rider/dashboard')->assertOk()->assertJsonPath('location_report_seconds', 15);

        // Before the rider app reports, the buyer sees the pin and a note.
        $this->signInMobile($this->buyer)->getJson("/api/mobile/orders/$orderId/tracking")->assertOk()
            ->assertJsonPath('tracking.destination.latitude', 14.2116)
            ->assertJsonPath('tracking.rider', null)
            ->assertJsonPath('tracking.message', 'Your rider is on the way. Their location appears once their app shares it.');

        $this->signInMobile($this->rider)->postJson('/api/mobile/rider/location', ['latitude' => 35.68, 'longitude' => 139.76])
            ->assertUnprocessable()->assertJsonPath('errors.latitude.0', 'That location is outside the Philippines.');
        $this->postJson('/api/mobile/rider/location', ['latitude' => 14.2201, 'longitude' => 121.1588])->assertOk()->assertJson(['updated' => 1]);

        $this->signInMobile($this->buyer)->getJson("/api/mobile/orders/$orderId/tracking")->assertOk()
            ->assertJsonPath('tracking.rider.latitude', 14.2201)
            ->assertJsonPath('tracking.rider.longitude', 121.1588)
            ->assertJsonPath('tracking.message', 'Your rider is on the way. The map updates every 15 seconds.');
        $this->getJson("/api/mobile/orders/$orderId")->assertOk()->assertJsonPath('order.tracking.rider.latitude', 14.2201);

        // Once delivered, the rider's position is no longer shown.
        DB::table('shipments')->where('id', $shipmentId)->update(['status' => 'delivered']);
        $this->getJson("/api/mobile/orders/$orderId/tracking")->assertOk()->assertJsonPath('tracking.rider', null)
            ->assertJsonPath('tracking.message', 'Your parcel was delivered.');

        // Other buyers cannot see it.
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($other)->getJson("/api/mobile/orders/$orderId/tracking")->assertNotFound();
    }

    public function test_reports_only_move_parcels_that_are_out_for_delivery(): void
    {
        [, $assigned] = $this->parcel('assigned');
        [, $onTheWay] = $this->parcel();

        $this->signInMobile($this->rider)->postJson('/api/mobile/rider/location', ['latitude' => 14.2, 'longitude' => 121.1])->assertOk()->assertJson(['updated' => 1]);

        $this->assertNull(DB::table('shipments')->where('id', $assigned)->value('rider_latitude'));
        $this->assertEquals(14.2, DB::table('shipments')->where('id', $onTheWay)->value('rider_latitude'));
    }

    public function test_the_website_shows_the_tracking_map(): void
    {
        [$orderId] = $this->parcel();

        $this->actingAs($this->buyer)->get(route('buyer.orders.track', $orderId))->assertOk()
            ->assertSee('Track your parcel')->assertSee('leaflet.min.js', false)->assertSee('js/order-tracking.js', false)
            ->assertSee('data-order-tracking', false);
        $this->getJson(route('buyer.orders.tracking', $orderId))->assertOk()->assertJsonPath('tracking.destination.longitude', 121.1653);

        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->get(route('buyer.orders.track', $orderId))->assertNotFound();
    }

    public function test_the_address_form_offers_a_map_pin(): void
    {
        $this->actingAs($this->buyer)->get(route('addresses.create'))->assertOk()
            ->assertSee('data-map-pin', false)->assertSee('name="latitude"', false)->assertSee('js/map-pin.js', false);
    }
}
