<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\User;
use App\Services\HubRouting;
use App\Services\ShipmentWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\LegacyOrderTables;
use Tests\TestCase;

class HubTransferTest extends TestCase
{
    use RefreshDatabase, LegacyOrderTables;

    private function hub(string $name, string $city, string $code, string $network = 'zaylo_express'): LogisticsProvider
    {
        return LogisticsProvider::create([
            'user_id' => User::factory()->create(['role' => 'logistics', 'status' => 'active'])->id,
            'name' => $name, 'slug' => Str::slug($name), 'status' => 'approved', 'network_brand' => $network,
            'city_name' => $city, 'city_code' => $code, 'province_name' => $city === 'Santa Cruz' ? 'Laguna' : 'Metro Manila',
        ]);
    }

    private function parcel(LogisticsProvider $origin, bool $local = false): Shipment
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $location = ['city' => $local ? 'Quezon City' : 'Santa Cruz', 'province' => $local ? 'Metro Manila' : 'Laguna',
            'city_municipality_code' => $local ? '137404000' : '043426000', 'barangay_code' => '043426001', 'barangay' => 'Barangay Uno'];
        $legacy = Order::usesLegacySchema();
        $order = Order::create([
            'payment_method' => 'cod', 'delivery_location' => $location,
        ] + ($legacy ? [
            'user_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'HUB-'.Str::uuid(),
            'status' => 'in_transit', 'subtotal' => 100, 'shipping_fee' => 120, 'total' => 220,
            'recipient_name' => 'Buyer', 'phone' => '09171234567', 'shipping_address' => 'Street, Barangay Uno, Santa Cruz, Laguna',
        ] : [
            'buyer_id' => $buyer->id, 'reference' => 'HUB-'.Str::uuid(), 'total_minor' => 22000,
            'shipping_address' => ['recipient' => 'Buyer', 'address' => 'Street, Barangay Uno, Santa Cruz, Laguna'],
        ]));
        $attributes = ['logistics_provider_id' => $origin->id, 'status' => 'in_transit', 'hub_received_at' => now()->subHour()];
        if ($legacy) return $order->shipment()->create($attributes + ['tracking_number' => 'HUB-TRACK-'.Str::random(6)]);
        $sellerOrder = $order->sellerOrders()->create(['seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 12000, 'commission_minor' => 1000, 'status' => 'shipped']);
        return $sellerOrder->shipment()->create($attributes + ['tracking_code' => 'HUB-TRACK-'.Str::random(6)]);
    }

    private function rider(LogisticsProvider $hub): Rider
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $rider = Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $hub->id, 'rider_type' => 'delivery', 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);
        $user->addresses()->create(['recipient' => $user->name, 'phone' => '09171234567', 'line1' => 'Home', 'barangay' => 'Barangay Uno', 'city' => $hub->city_name, 'province' => $hub->province_name, 'is_default' => true]);
        $rider->attendances()->create(['logistics_provider_id' => $hub->id, 'work_date' => now('Asia/Manila')->toDateString(), 'time_in_at' => now()->subHour()]);
        return $rider;
    }

    private function legacySchema(): void
    {
        $this->useLegacyOrderTables();
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('logistics_provider_id')->nullable();
            $table->unsignedBigInteger('pickup_courier_id')->nullable();
            $table->timestamp('pickup_confirmed_at')->nullable();
            $table->date('pickup_scheduled_for')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->string('sort_area', 120)->nullable();
            $table->timestamp('hub_received_at')->nullable();
        });
        (require database_path('migrations/2026_11_17_000004_add_hub_to_hub_routing.php'))->up();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('schemas')]
    public function test_origin_hub_transfer_destination_receipt_barangay_sorting_and_local_rider_assignment(bool $legacy): void
    {
        if ($legacy) $this->legacySchema();
        $origin = $this->hub('Metro Manila Hub', 'Quezon City', '137404000');
        $destination = $this->hub('Santa Cruz Hub', 'Santa Cruz', '043426000');
        $shipment = $this->parcel($origin);
        $tracking = $shipment->tracking_number;
        $area = 'Barangay Uno, Santa Cruz, Laguna';
        $firstReceipt = $shipment->hub_received_at;
        $destinationRider = $this->rider($destination);
        $originRider = $this->rider($origin);
        app(HubRouting::class)->prepare($shipment);
        $this->assertSame($destination->id, $shipment->destination_hub_id);

        $this->actingAs($origin->owner)->get(route('logistics.sorting'))->assertOk()->assertSee('Transfer to destination hub');
        $this->patch(route('logistics.parcels.sort', $shipment), ['area' => $area])->assertSessionHasErrors('shipment');
        $this->get(route('logistics.hub-transfers'))->assertOk()->assertSee($destination->name);
        $this->post(route('logistics.hub-transfers.dispatch', $shipment), ['destination_hub_id' => $destination->id, 'tracking' => 'WRONG'])->assertSessionHasErrors('tracking');
        $this->post(route('logistics.hub-transfers.dispatch', $shipment), ['destination_hub_id' => $destination->id, 'tracking' => $tracking])->assertSessionHasNoErrors();
        $this->assertSame('hub_transfer', ShipmentWorkflow::stageOf($shipment->fresh()));
        $this->assertSame(0, ShipmentWorkflow::whereStage(Shipment::query(), ['in_transit', 'sorted', 'assigned'])->count());
        $this->assertSame(1, ShipmentWorkflow::whereStage(Shipment::query(), ['hub_transfer'])->count());
        $this->post(route('logistics.hub-transfers.dispatch', $shipment), ['destination_hub_id' => $destination->id, 'tracking' => $tracking])->assertSessionHasErrors('shipment');
        $checks = ['tracking' => $tracking, 'parcel_received' => 1, 'tracking_verified' => 1];
        $this->post(route('logistics.hub-transfers.receive', $shipment), $checks)->assertForbidden();

        $this->actingAs($destination->owner)->get(route('logistics.hub-transfers'))->assertOk()->assertSee($tracking)->assertSee($origin->name);
        $this->post(route('logistics.parcels.assign', $shipment), ['rider_id' => $destinationRider->id])->assertForbidden();
        $this->post(route('logistics.hub-transfers.receive', $shipment), ['tracking' => $tracking])->assertSessionHasErrors(['parcel_received', 'tracking_verified']);
        $this->post(route('logistics.hub-transfers.receive', $shipment), $checks)->assertRedirect(route('logistics.sorting'));
        $shipment->refresh();
        $this->assertSame($destination->id, $shipment->current_hub_id);
        $this->assertSame($origin->id, $shipment->logistics_provider_id, 'Contracted provider is preserved for settlement.');
        $this->assertEquals($firstReceipt, $shipment->hub_received_at, 'First-mile receipt is preserved.');
        $this->assertSame('in_transit', ShipmentWorkflow::stageOf($shipment));
        $this->post(route('logistics.hub-transfers.receive', $shipment), $checks)->assertSessionHasErrors('shipment');
        $this->get(route('logistics.sorting'))->assertSee($tracking)->assertSee($area);
        $this->patch(route('logistics.parcels.sort', $shipment), ['area' => 'A different barangay'])->assertSessionHasErrors('area');
        $this->patch(route('logistics.parcels.sort', $shipment), ['area' => $area])->assertSessionHasNoErrors();
        $this->assertSame('sorted', ShipmentWorkflow::stageOf($shipment->fresh()));
        $this->post(route('logistics.parcels.assign', $shipment), ['rider_id' => $originRider->id])->assertSessionHasErrors('rider_id');
        $this->actingAs($origin->owner)->patch(route('logistics.parcels.sort', $shipment), ['area' => $area])->assertForbidden();
        $this->actingAs($destination->owner)->post(route('logistics.parcels.assign', $shipment), ['rider_id' => $destinationRider->id])->assertSessionHasNoErrors();
        $this->assertSame('assigned', ShipmentWorkflow::stageOf($shipment->fresh()));
        $this->assertSame($destinationRider->user_id, $shipment->fresh()->riderUserId());
        $order = $shipment->order ?: $shipment->sellerOrder->order;
        $this->assertSame(1, $order->statusHistory()->where('note', 'like', 'Dispatched from%')->count());
        $this->assertSame(1, $order->statusHistory()->where('note', 'like', 'Received at destination hub%')->count());
    }

    public static function schemas(): array { return ['fresh' => [false], 'deployed legacy' => [true]]; }

    public function test_cross_network_wrong_city_suspended_and_unrelated_hubs_cannot_take_a_parcel(): void
    {
        $origin = $this->hub('Origin', 'Quezon City', '137404000');
        $wrongNetwork = $this->hub('Different Network', 'Santa Cruz', '043426000', 'jnt_express');
        $wrongCity = $this->hub('Different City', 'Pasig City', '137403000');
        $suspended = $this->hub('Suspended Hub', 'Santa Cruz', '043426000');
        $suspended->update(['status' => 'suspended']);
        $shipment = $this->parcel($origin);
        foreach ([$wrongNetwork, $wrongCity, $suspended] as $hub) {
            $this->actingAs($origin->owner)->post(route('logistics.hub-transfers.dispatch', $shipment), ['destination_hub_id' => $hub->id, 'tracking' => $shipment->tracking_number])->assertSessionHasErrors('destination_hub_id');
        }
        $this->actingAs($wrongCity->owner)->post(route('logistics.hub-transfers.dispatch', $shipment), ['destination_hub_id' => $wrongNetwork->id, 'tracking' => $shipment->tracking_number])->assertForbidden();
        $this->assertNull($shipment->fresh()->hub_dispatched_at);
        $this->assertSame('in_transit', ShipmentWorkflow::stageOf($shipment->fresh()));
    }

    public function test_local_parcels_sort_by_barangay_without_a_hub_transfer(): void
    {
        $hub = $this->hub('Local Hub', 'Quezon City', '137404000');
        $shipment = $this->parcel($hub, local: true);
        app(HubRouting::class)->prepare($shipment);
        $this->assertFalse(HubRouting::needsTransfer($shipment));
        $this->actingAs($hub->owner)->patch(route('logistics.parcels.sort', $shipment), ['area' => 'Barangay Uno, Quezon City, Metro Manila'])->assertSessionHasNoErrors();
        $this->assertSame('sorted', ShipmentWorkflow::stageOf($shipment->fresh()));
        $this->assertNull($shipment->fresh()->hub_dispatched_at);
    }

    public function test_checkout_copies_location_codes_and_later_address_edits_do_not_reroute_the_order(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $address = $buyer->addresses()->create([
            'recipient' => 'Buyer', 'phone' => '09171234567', 'line1' => 'House 1', 'region_code' => '040000000',
            'region_name' => 'CALABARZON', 'province_code' => '043400000', 'province' => 'Laguna',
            'city_municipality_code' => '043426000', 'city' => 'Santa Cruz', 'barangay_code' => '043426001', 'barangay' => 'Barangay Uno',
        ]);
        $product = \App\Models\Product::create(['seller_id' => $seller->id, 'name' => 'Route fixture', 'category' => 'blenders', 'price' => 100, 'stock' => 5, 'is_active' => true]);
        $item = app(\App\Services\CartService::class)->add($buyer, $product, 1);
        $order = app(\App\Services\CheckoutService::class)->place($buyer, [$item->id], [
            'address_id' => $address->id, 'payment_method' => 'cod', 'idempotency_key' => (string) Str::uuid(),
        ], '')->sole();
        $this->assertSame('043426000', $order->delivery_location['city_municipality_code']);
        $this->assertSame('043426001', $order->delivery_location['barangay_code']);
        $address->update(['city' => 'Another city', 'barangay' => 'Another barangay']);
        $this->assertSame('Santa Cruz', $order->fresh()->delivery_location['city']);
        $this->assertSame('Barangay Uno', $order->fresh()->delivery_location['barangay']);
    }

    public function test_rider_road_distances_use_origin_for_pickup_and_destination_for_last_mile(): void
    {
        $origin = $this->hub('Origin Pin Hub', 'Quezon City', '137404000');
        $destination = $this->hub('Destination Pin Hub', 'Santa Cruz', '043426000');
        $origin->update(['hub_latitude' => 14.6, 'hub_longitude' => 121.0]);
        $destination->update(['hub_latitude' => 14.28, 'hub_longitude' => 121.42]);
        $shipment = $this->parcel($origin);
        $order = $shipment->sellerOrder->order;
        $order->update(['delivery_latitude' => 14.29, 'delivery_longitude' => 121.43]);
        $seller = $shipment->sellerOrder->seller->owner;
        $seller->addresses()->create(['recipient' => 'Seller', 'phone' => '09171234567', 'line1' => 'Origin shop', 'barangay' => 'Seller Barangay', 'city' => 'Quezon City', 'province' => 'Metro Manila', 'latitude' => 14.61, 'longitude' => 121.01, 'is_default' => true]);
        $product = \App\Models\Product::create(['seller_id' => $seller->id, 'name' => 'Weighted route fixture', 'category' => 'blenders', 'price' => 100, 'stock' => 5, 'weight_grams' => 1000]);
        $shipment->sellerOrder->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id, 'product_name' => $product->name, 'variant_name' => 'Default', 'unit_price_minor' => 10000, 'quantity' => 1]);
        $requests = [];
        \Illuminate\Support\Facades\Http::fake(function ($request) use (&$requests) {
            $requests[] = $request->url();
            return \Illuminate\Support\Facades\Http::response(['code' => 'Ok', 'routes' => [['distance' => count($requests) === 1 ? 10000 : 2000]]]);
        });
        $quote = app(\App\Services\RiderLegEarnings::class)->quote($shipment);
        $this->assertSame(10.0, $quote['pickup_km']);
        $this->assertSame(2.0, $quote['delivery_km']);
        $this->assertStringContainsString('121.01,14.61;121,14.6', $requests[0]);
        $this->assertStringContainsString('121.42,14.28;121.43,14.29', $requests[1]);
        $this->assertLessThanOrEqual(120, $quote['total']);
    }

    public function test_destination_delivery_commission_belongs_to_the_destination_riders_hub(): void
    {
        $origin = $this->hub('Origin Payroll Hub', 'Quezon City', '137404000');
        $destination = $this->hub('Destination Payroll Hub', 'Santa Cruz', '043426000');
        $shipment = $this->parcel($origin);
        $rider = $this->rider($destination);
        $shipment->update([
            'origin_hub_id' => $origin->id, 'current_hub_id' => $destination->id, 'destination_hub_id' => $destination->id,
            'destination_received_at' => now(), 'status' => 'delivered', 'rider_id' => $rider->id,
            'delivery_proof_path' => 'fixture-proof.jpg',
            'rider_fee_quote' => ['pickup_amount' => 20, 'pickup_km' => 2, 'delivery_amount' => 30, 'delivery_km' => 3, 'weight_kg' => 1, 'total' => 50],
        ]);
        $commission = app(\App\Services\RiderLegEarnings::class)->credit($shipment, $rider->user, 'delivery');
        $this->assertSame($destination->id, $commission->logistics_provider_id);
        $this->assertEquals(30, $commission->amount);
        app(\App\Services\RiderLegEarnings::class)->credit($shipment, $rider->user, 'delivery');
        $this->assertDatabaseCount('rider_leg_commissions', 1);
    }
}
