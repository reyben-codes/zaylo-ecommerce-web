<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesSignUp;
use Tests\TestCase;

class CourierAndRiderSeparationTest extends TestCase
{
    use CompletesSignUp;
    use RefreshDatabase;

    private LogisticsProvider $provider;
    private User $logisticsOwner;
    private User $sellerUser;
    private mixed $shop;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();

        $this->logisticsOwner = User::factory()->create([
            'role' => 'logistics',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->provider = LogisticsProvider::create([
            'user_id' => $this->logisticsOwner->id,
            'name' => 'Flash Express Hub',
            'slug' => 'flash-express-hub',
            'status' => 'approved',
            'service_area' => 'Metro Manila',
        ]);

        $this->sellerUser = User::factory()->create([
            'role' => 'seller',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone' => '09170001111',
        ]);
        $this->sellerUser->addresses()->create([
            'label' => 'Warehouse',
            'recipient' => 'Seller Store',
            'phone' => '09170001111',
            'line1' => '123 Warehouse St',
            'barangay' => 'Barangay 1',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'is_default' => true,
        ]);
        $this->shop = $this->sellerUser->sellers()->create([
            'name' => 'Mega Seller Shop',
            'slug' => 'mega-seller-shop',
            'status' => 'approved',
        ]);

        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_courier_and_rider_registration_and_portal_routing(): void
    {
        // 1. Register courier (first-mile driver)
        $courierUser = $this->signUp([
            'first_name' => 'Carlos',
            'last_name' => 'Courier',
            'email' => 'carlos@example.com',
            'phone' => '09171112222',
            'date_of_birth' => '1995-05-15',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'courier',
            'logistics_provider_id' => $this->provider->id,
            'vehicle_type' => 'Van',
            'plate_no' => 'ABC-9988',
            'license_number' => 'N01-23-456789',
            'license_expires_on' => now()->addYear()->toDateString(),
            'license_document' => UploadedFile::fake()->image('license.jpg'),
            'or_cr_document' => UploadedFile::fake()->image('orcr.jpg'),
            'terms' => '1',
        ]);

        $this->assertSame('courier', $courierUser->role);
        $this->assertTrue($courierUser->isCourier());
        $this->assertFalse($courierUser->isRider());
        $this->assertSame('courier.login', $courierUser->portalLoginRoute());

        // 2. Register rider (last-mile driver)
        $riderUser = $this->signUp([
            'first_name' => 'Randy',
            'last_name' => 'Rider',
            'email' => 'randy@example.com',
            'phone' => '09173334444',
            'date_of_birth' => '1996-06-20',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'rider',
            'logistics_provider_id' => $this->provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'XYZ-1234',
            'license_number' => 'N02-45-789012',
            'license_expires_on' => now()->addYear()->toDateString(),
            'license_document' => UploadedFile::fake()->image('license.jpg'),
            'or_cr_document' => UploadedFile::fake()->image('orcr.jpg'),
            'terms' => '1',
        ]);

        $this->assertSame('rider', $riderUser->role);
        $this->assertTrue($riderUser->isRider());
        $this->assertFalse($riderUser->isCourier());
        $this->assertSame('rider.app', $riderUser->portalLoginRoute());
    }

    public function test_logistics_provider_can_manage_couriers_and_riders_separately(): void
    {
        // Create pending courier
        $courierUser = User::factory()->create(['role' => 'courier', 'status' => 'pending', 'name' => 'Pending Courier']);
        $courierRecord = Rider::create([
            'user_id' => $courierUser->id,
            'logistics_provider_id' => $this->provider->id,
            'vehicle_type' => 'Van / L300',
            'plate_no' => 'VAN-111',
            'license_number' => 'N01-11-111111',
            'status' => 'pending',
            'is_active' => true,
        ]);

        // Create pending rider
        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'pending', 'name' => 'Pending Rider']);
        $riderRecord = Rider::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $this->provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'MOTO-222',
            'license_number' => 'N02-22-222222',
            'status' => 'pending',
            'is_active' => true,
        ]);

        // Provider views couriers page
        $couriersPage = $this->actingAs($this->logisticsOwner)
            ->get(route('logistics.couriers', ['status' => 'pending']));
        $couriersPage->assertOk()
            ->assertSee('Pending Courier')
            ->assertSee('VAN-111')
            ->assertDontSee('MOTO-222');

        // Provider views riders page
        $ridersPage = $this->actingAs($this->logisticsOwner)
            ->get(route('logistics.riders', ['status' => 'pending']));
        $ridersPage->assertOk()
            ->assertSee('Pending Rider')
            ->assertSee('MOTO-222')
            ->assertDontSee('VAN-111');

        // Approve both
        $this->actingAs($this->logisticsOwner)
            ->patch(route('logistics.riders.update', $courierRecord), ['action' => 'approve'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $courierRecord->fresh()->status);
        $this->assertSame('active', $courierUser->fresh()->status);

        $this->actingAs($this->logisticsOwner)
            ->patch(route('logistics.riders.update', $riderRecord), ['action' => 'approve'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $riderRecord->fresh()->status);
        $this->assertSame('active', $riderUser->fresh()->status);
    }

    public function test_complete_first_mile_courier_and_last_mile_rider_flow(): void
    {
        // 1. Setup Approved Courier and Approved Rider
        $courierUser = User::factory()->create(['role' => 'courier', 'status' => 'active', 'email_verified_at' => now()]);
        Rider::create([
            'user_id' => $courierUser->id,
            'logistics_provider_id' => $this->provider->id,
            'vehicle_type' => 'Van / L300',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'email_verified_at' => now()]);
        $riderRecord = Rider::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $this->provider->id,
            'vehicle_type' => 'Motorcycle',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $riderUser->addresses()->create(['recipient' => 'Rider', 'phone' => '09170000001', 'line1' => 'Home', 'barangay' => 'Central', 'city' => 'Makati', 'province' => 'Metro Manila', 'is_default' => true]);
        // Time in the rider so they can receive parcels at the hub
        $riderRecord->attendances()->create([
            'logistics_provider_id' => $this->provider->id,
            'work_date' => now('Asia/Manila')->toDateString(),
            'time_in_at' => now()->subHour(),
        ]);

        // 2. Create Order & SellerOrder & Shipment
        $order = Order::create([
            'buyer_id' => $this->buyer->id,
            'reference' => 'ZAY-FLOW-001',
            'total_minor' => 150000,
            'payment_method' => 'cod',
            'shipping_address' => [
                'recipient' => 'Buyer Juan',
                'phone' => '09189998888',
                'line1' => '456 Buyer Ave',
                'barangay' => 'San Antonio',
                'city' => 'Makati',
                'province' => 'Metro Manila',
            ],
            'courier_choice' => 'flash_express',
        ]);

        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $this->shop->id,
            'logistics_provider_id' => $this->provider->id,
            'subtotal_minor' => 150000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 0,
            'status' => 'ready_to_ship',
        ]);

        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => $this->provider->id,
            'tracking_code' => 'TRK-ZAY-FLOW-101',
            'status' => 'ready',
        ]);

        // 3. Logistics Partner confirms pickup and assigns first-mile Courier
        $pickupDate = now('Asia/Manila')->addDay()->toDateString();
        $res = $this->actingAs($this->logisticsOwner)
            ->patch(route('logistics.parcels.pickup', $shipment), [
                'decision' => 'confirm',
                'pickup_date' => $pickupDate,
                'courier_id' => $courierUser->id,
            ]);
        $res->assertSessionHasNoErrors();
        $res->assertRedirect();

        $shipment->refresh();
        $this->assertSame('confirmed', \App\Services\ShipmentWorkflow::stageOf($shipment));
        $this->assertSame($courierUser->id, (int) $shipment->pickup_courier_id);

        // 4. Courier logs in, checks /courier/deliveries, sees seller pickup address
        $courierDeliveries = $this->actingAs($courierUser)
            ->get(route('courier.deliveries'));
        $courierDeliveries->assertOk()
            ->assertSee('TRK-ZAY-FLOW-101')
            ->assertSee('123 Warehouse St')
            ->assertSee('09170001111');

        // 5. Courier visits seller, enters waybill code to verify and confirm pickup
        $this->actingAs($courierUser)
            ->patch(route('courier.deliveries.update', $shipment), [
                'status' => 'picked_up',
                'tracking' => 'WRONG-CODE',
            ])
            ->assertSessionHasErrors('tracking');

        $this->actingAs($courierUser)
            ->patch(route('courier.deliveries.update', $shipment), [
                'status' => 'picked_up',
                'tracking' => 'TRK-ZAY-FLOW-101',
            ])
            ->assertSessionHasNoErrors();

        $shipment->refresh();
        $this->assertSame('picked_up', $shipment->status);
        $this->assertSame($courierUser->id, (int) $shipment->pickup_courier_id);

        // 6. Courier delivers parcel to sorting center (handover to hub)
        $this->actingAs($courierUser)
            ->patch(route('courier.deliveries.update', $shipment), [
                'status' => 'in_transit',
            ])
            ->assertSessionHasNoErrors();

        $shipment->refresh();
        $this->assertSame('in_transit', $shipment->status);

        // 7. Hub sorts parcel for Makati area
        $this->actingAs($this->logisticsOwner)
            ->patch(route('logistics.parcels.sort', $shipment), [
                'area' => 'Makati, Metro Manila',
            ])
            ->assertSessionHasNoErrors();

        $shipment->refresh();
        $this->assertSame('sorted', \App\Services\ShipmentWorkflow::stageOf($shipment));

        // 8. Hub assigns Last-Mile Rider for delivery to buyer
        $this->actingAs($this->logisticsOwner)
            ->post(route('logistics.parcels.assign', $shipment), [
                'rider_id' => $riderRecord->id,
            ])
            ->assertSessionHasNoErrors();

        $shipment->refresh();
        $this->assertSame('assigned', $shipment->status);
        // Ensure both roles remain distinct on the shipment:
        $this->assertSame($courierUser->id, (int) $shipment->pickup_courier_id);
        $this->assertSame($riderRecord->id, (int) ($shipment->rider_id ?? $shipment->courier_id));
    }
}
