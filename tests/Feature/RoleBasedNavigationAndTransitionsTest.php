<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class RoleBasedNavigationAndTransitionsTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    public function test_rbac_middleware_strictly_separates_pickup_and_delivery_riders(): void
    {
        $logisticsOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsOwner->id,
            'name' => 'Metro Logistics',
            'slug' => 'metro-logistics',
            'status' => 'approved',
        ]);

        // Create Pickup Rider
        $pickupUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $pickupRider = Rider::create([
            'user_id' => $pickupUser->id,
            'logistics_provider_id' => $provider->id,
            'rider_type' => 'pickup',
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Create Delivery Rider
        $deliveryUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $deliveryRider = Rider::create([
            'user_id' => $deliveryUser->id,
            'logistics_provider_id' => $provider->id,
            'rider_type' => 'delivery',
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Pickup rider accessing Pickup endpoints -> OK
        $this->signInMobile($pickupUser);
        $this->getJson('/api/mobile/rider/pickup/queue')->assertOk()->assertJsonPath('role', 'pickup_rider');
        $this->getJson('/api/mobile/rider/pickup/manifest')->assertOk()->assertJsonPath('role', 'pickup_rider');
        $this->getJson('/api/mobile/rider/pickup/dropoff')->assertOk()->assertJsonPath('role', 'pickup_rider');

        // Pickup rider accessing Delivery endpoints -> 403 Forbidden
        $this->getJson('/api/mobile/rider/delivery/manifest')->assertForbidden()->assertJsonPath('code', 'delivery_rider_only');
        $this->postJson('/api/mobile/rider/delivery/scan', ['code' => 'XYZ'])->assertForbidden()->assertJsonPath('code', 'delivery_rider_only');

        // Delivery rider accessing Delivery endpoints -> OK
        $this->signInMobile($deliveryUser);
        $this->getJson('/api/mobile/rider/delivery/manifest')->assertOk()->assertJsonPath('role', 'delivery_rider');

        // Delivery rider accessing Pickup endpoints -> 403 Forbidden
        $this->getJson('/api/mobile/rider/pickup/queue')->assertForbidden()->assertJsonPath('code', 'pickup_rider_only');
        $this->getJson('/api/mobile/rider/pickup/manifest')->assertForbidden()->assertJsonPath('code', 'pickup_rider_only');
        $this->postJson('/api/mobile/rider/pickup/scan', ['code' => 'XYZ'])->assertForbidden()->assertJsonPath('code', 'pickup_rider_only');
    }

    public function test_end_to_end_parcel_lifecycle_transitions(): void
    {
        Storage::fake('local');

        // 1. Setup Actors: Seller, Logistics Hub, Pickup Rider, Delivery Rider, Buyer
        $logisticsOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsOwner->id,
            'name' => 'Swift Logistics Hub',
            'slug' => 'swift-hub',
            'status' => 'approved',
        ]);

        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $sellerAddress = $sellerUser->addresses()->create([
            'recipient' => 'Seller Warehouse',
            'phone' => '09171112233',
            'barangay' => 'Central',
            'line1' => 'Warehouse 1',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'is_default' => true,
        ]);
        $shop = $sellerUser->sellerShop();
        $shop->update(['pickup_address_id' => $sellerAddress->id]);

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $pickupUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => 'Pedro Pickup']);
        $pickupRider = Rider::create([
            'user_id' => $pickupUser->id,
            'logistics_provider_id' => $provider->id,
            'rider_type' => 'pickup',
            'status' => 'approved',
            'is_active' => true,
            'plate_no' => 'ABC1234',
            'vehicle_type' => 'Motorcycle',
        ]);
        $pickupUser->addresses()->create([
            'recipient' => 'Pedro',
            'phone' => '09172223344',
            'barangay' => 'Central',
            'line1' => 'Home',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'is_default' => true,
        ]);
        $pickupRider->attendances()->create([
            'logistics_provider_id' => $provider->id,
            'work_date' => now('Asia/Manila')->toDateString(),
            'time_in_at' => now(),
        ]);

        $deliveryUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => 'Diego Delivery']);
        $deliveryRider = Rider::create([
            'user_id' => $deliveryUser->id,
            'logistics_provider_id' => $provider->id,
            'rider_type' => 'delivery',
            'status' => 'approved',
            'is_active' => true,
            'plate_no' => 'XYZ5678',
            'vehicle_type' => 'Motorcycle',
        ]);
        $deliveryUser->addresses()->create([
            'recipient' => 'Diego',
            'phone' => '09173334455',
            'barangay' => 'Central',
            'line1' => 'Home',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'is_default' => true,
        ]);
        $deliveryRider->attendances()->create([
            'logistics_provider_id' => $provider->id,
            'work_date' => now('Asia/Manila')->toDateString(),
            'time_in_at' => now(),
        ]);

        // Create Order & Product
        $product = Product::create([
            'seller_id' => $sellerUser->id,
            'name' => 'Wireless Keyboard',
            'category' => 'electronics',
            'price' => 1500,
            'stock' => 10,
            'is_active' => true,
        ]);

        $orderData = [
            'buyer_id' => $buyer->id,
            'reference' => 'ORD-TEST-001',
            'total_minor' => 150000,
            'payment_method' => 'cod',
            'shipping_address' => ['recipient' => 'Buyer Maria', 'phone' => '09174445566', 'city' => 'Manila'],
        ];
        if (Order::usesLegacySchema()) {
            $orderData['seller_id'] = $shop->id;
            $orderData['order_number'] = 'ORD-TEST-001';
            $orderData['status'] = 'processing';
        }
        $order = Order::create($orderData);

        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $shop->id,
            'subtotal_minor' => 150000,
            'shipping_fee_minor' => 5000,
            'commission_minor' => 0,
            'status' => 'packed',
        ]);

        $sellerOrder->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->firstOrFail()->id,
            'product_name' => 'Wireless Keyboard',
            'variant_name' => 'Black',
            'unit_price_minor' => 150000,
            'quantity' => 1,
        ]);

        // ----------------------------------------------------
        // TRANSITION 1: PICKUP_SCHEDULED (Triggered by Seller)
        // ----------------------------------------------------
        $this->actingAs($sellerUser, 'web')
            ->get(route('seller.pickups'))
            ->assertOk()
            ->assertSee('Pickup Management')
            ->assertSee('ORD-TEST-001');

        $this->actingAs($sellerUser, 'web')
            ->post(route('seller.pickups.schedule'), [
                'order_id' => $order->id,
                'logistics_provider_id' => $provider->id,
                'pickup_date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $shipment = Shipment::where('order_id', $order->id)->orWhere('seller_order_id', $sellerOrder->id)->firstOrFail();
        $this->assertSame('ready', ShipmentWorkflow::stageOf($shipment));

        // ----------------------------------------------------
        // TRANSITION 2: PICKUP_ASSIGNED (Triggered by Logistics)
        // ----------------------------------------------------
        $shipment->update(['pickup_confirmed_at' => now()]);

        $this->actingAs($logisticsOwner, 'web')
            ->post(route('logistics.parcels.pickup-rider', $shipment), [
                'rider_id' => $pickupRider->id,
            ])
            ->assertSessionHasNoErrors();

        $shipment->refresh();
        $this->assertSame($pickupUser->id, $shipment->pickup_courier_id);

        // Pickup Rider sees it in queue
        $this->signInMobile($pickupUser);
        $queueRes = $this->getJson('/api/mobile/rider/pickup/queue')->assertOk();
        $this->assertSame(1, $queueRes->json('count'));
        $this->assertSame($shipment->tracking_number, $queueRes->json('queue.0.tracking_number'));

        // ----------------------------------------------------
        // TRANSITION 3: PICKED_UP_IN_TRANSIT (Pickup Rider scan)
        // ----------------------------------------------------
        $scanRes = $this->postJson('/api/mobile/rider/pickup/scan', [
            'code' => $shipment->tracking_number,
        ])->assertOk();

        $this->assertSame('picked_up_in_transit', $scanRes->json('status'));
        $shipment->refresh();
        $this->assertNotNull($shipment->pickup_scanned_at);
        $this->assertSame('picked_up', ShipmentWorkflow::stageOf($shipment));

        // Syncs to active transit manifest
        $manifestRes = $this->getJson('/api/mobile/rider/pickup/manifest')->assertOk();
        $this->assertSame(1, $manifestRes->json('count'));
        $this->assertSame($shipment->tracking_number, $manifestRes->json('manifest.0.tracking_number'));

        // Seller confirms doorstep handover
        $this->withoutToken()->actingAs($sellerUser, 'web')
            ->post(route('seller.pickups.confirm-handover', $order))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($shipment->fresh()->handover_confirmed_at);

        // ----------------------------------------------------
        // TRANSITION 4: ARRIVED_AT_HUB (Logistics confirms receipt)
        // ----------------------------------------------------
        // Terminal state for Pickup Rider: removes parcel from active manifest and moves to dropoff history
        $this->actingAs($logisticsOwner, 'web')
            ->patch(route('logistics.parcels.advance', $shipment), [
                'status' => 'in_transit',
                'parcel_received' => '1',
                'tracking_verified' => '1',
                'items_checked' => '1',
            ])
            ->assertSessionHasNoErrors();

        $shipment->refresh();
        $this->assertNotNull($shipment->hub_received_at);
        $this->assertSame($logisticsOwner->id, $shipment->hub_received_by);

        // Pickup Rider's active manifest is now empty (terminal state completed)
        $this->signInMobile($pickupUser);
        $this->getJson('/api/mobile/rider/pickup/manifest')->assertOk()->assertJsonPath('count', 0);
        $this->getJson('/api/mobile/rider/pickup/dropoff')->assertOk()->assertJsonPath('count', 1);

        // Seller portal shows Arrived at Hub
        $this->withoutToken()->actingAs($sellerUser, 'web')
            ->get(route('seller.pickups'))
            ->assertOk()
            ->assertSee('Hub Arrival Tracking')
            ->assertSee($shipment->tracking_number);

        // ----------------------------------------------------
        // TRANSITION 5: OUT_FOR_DELIVERY (Logistics assigns Delivery Rider)
        // ----------------------------------------------------
        $this->actingAs($logisticsOwner, 'web')
            ->patch(route('logistics.parcels.sort', $shipment), ['area' => 'Manila, Metro Manila'])
            ->assertSessionHasNoErrors();

        $this->actingAs($logisticsOwner, 'web')
            ->post(route('logistics.parcels.assign', $shipment), ['rider_id' => $deliveryRider->id])
            ->assertSessionHasNoErrors();

        $shipment->refresh();
        $this->assertSame($deliveryRider->id, $shipment->rider_id);

        // Delivery Rider accepts assignment and starts delivery
        $this->signInMobile($deliveryUser);
        $this->getJson('/api/mobile/rider/delivery/manifest')->assertOk()->assertJsonPath('count', 1);

        $this->postJson("/api/mobile/rider/deliveries/{$shipment->id}/accept")->assertOk();

        $this->postJson('/api/mobile/rider/delivery/scan', ['code' => $shipment->tracking_number])
            ->assertOk()
            ->assertJsonPath('step', 'out_for_delivery');

        $this->assertSame('out_for_delivery', ShipmentWorkflow::stageOf($shipment->fresh()));

        // ----------------------------------------------------
        // TRANSITION 6: DELIVERED (Delivery Rider completes with POD)
        // ----------------------------------------------------
        $file = UploadedFile::fake()->image('pod.jpg', 600, 600);
        $this->postJson('/api/mobile/rider/delivery/scan', [
            'code' => $shipment->tracking_number,
            'photo' => $file,
        ])->assertOk()->assertJsonPath('step', 'delivered');

        $shipment->refresh();
        $this->assertSame('delivered', ShipmentWorkflow::stageOf($shipment));
        $this->assertNotNull($shipment->delivery_proof_path);
    }
}
