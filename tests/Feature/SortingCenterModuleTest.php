<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortingCenterModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sorting_center_pages_render_for_an_authenticated_sorting_center_user(): void
    {
        $user = User::factory()->create([
            'role' => 'sorting_center',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $overview = $this->get(route('sorting-center.dashboard'))->assertOk();
        foreach ([
            'sorting-center.dashboard',
            'sorting-center.riders',
            'sorting-center.pickups',
            'sorting-center.incoming',
            'sorting-center.sorting',
            'sorting-center.assignments',
            'sorting-center.monitoring',
            'sorting-center.reports',
            'sorting-center.chat',
            'sorting-center.account',
        ] as $routeName) {
            $overview->assertSee(route($routeName), false);
        }
        $overview->assertSee(route('logout'), false)->assertSee('name="_token"', false);

        foreach ([
            ['sorting-center.dashboard', ['search' => 'missing']],
            ['sorting-center.riders', []],
            ['sorting-center.pickups', []],
            ['sorting-center.incoming', ['search' => 'missing']],
            ['sorting-center.sorting', ['tracking' => 'missing']],
            ['sorting-center.assignments', ['area' => 'Pasig']],
            ['sorting-center.monitoring', ['search' => 'missing', 'status' => 'delivered']],
            ['sorting-center.reports', ['from' => '2026-01-01', 'to' => '2026-12-31']],
            ['sorting-center.chat', []],
            ['sorting-center.account', []],
        ] as [$routeName, $query]) {
            $this->get(route($routeName, $query))->assertOk();
        }
    }

    public function test_sorting_center_can_approve_a_pending_courier_application(): void
    {
        $center = User::factory()->create([
            'role' => 'sorting_center',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $applicant = User::factory()->create([
            'role' => 'courier',
            'status' => 'pending',
        ]);

        $this->actingAs($center)
            ->post(route('sorting-center.riders.applications.update', $applicant), ['decision' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $applicant->id,
            'status' => 'active',
            'approved_by' => $center->id,
        ]);
    }

    public function test_sorting_center_can_scan_and_assign_a_live_shipment(): void
    {
        $center = User::factory()->create([
            'role' => 'sorting_center',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $providerUser = User::factory()->create(['role' => 'logistics']);
        $provider = LogisticsProvider::create([
            'user_id' => $providerUser->id,
            'name' => 'ZAYLO Express',
            'slug' => 'zaylo-express',
            'status' => 'approved',
        ]);
        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'ABC-123',
        ]);
        $sellerUser = User::factory()->create(['role' => 'seller']);
        $seller = $sellerUser->sellers()->create([
            'name' => 'Northline Goods',
            'slug' => 'northline-goods',
            'status' => 'approved',
        ]);
        $buyer = User::factory()->create(['role' => 'buyer']);
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'reference' => 'ZAY-TEST-001',
            'total_minor' => 10000,
            'payment_method' => 'cod',
            'shipping_address' => ['recipient' => 'Buyer', 'city' => 'Pasig City'],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 0,
        ]);
        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => $provider->id,
            'tracking_code' => 'ZX-TEST-001',
            'status' => 'unassigned',
        ]);

        $this->actingAs($center)
            ->get(route('sorting-center.sorting', ['tracking' => 'ZX-TEST-001']))
            ->assertOk()
            ->assertSee('ZX-TEST-001');

        $this->get(route('sorting-center.assignments', ['area' => 'Pasig']))
            ->assertOk()
            ->assertSee('ZX-TEST-001')
            ->assertSee($riderUser->name);

        $this->post(route('sorting-center.assignments.store', $shipment), ['rider_id' => $rider->id])
            ->assertRedirect();

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'rider_id' => $rider->id,
            'status' => 'assigned',
        ]);

        $this->patch(route('sorting-center.shipments.status', $shipment), ['status' => 'picked_up'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Shipment status updated. Order status history is not enabled in this installation.');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => 'picked_up',
        ]);

        $this->patch(route('sorting-center.riders.status', $rider), ['is_active' => 0])
            ->assertRedirect();

        $this->assertDatabaseHas('riders', [
            'id' => $rider->id,
            'is_active' => 0,
        ]);
    }
}
