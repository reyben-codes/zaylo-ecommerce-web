<?php

namespace Tests\Feature;

use App\Models\RiderApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileRiderDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function rider(): User
    {
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active', 'phone' => '09556644889']);
        RiderApplication::create([
            'user_id' => $rider->id, 'license_number' => 'S33-45-666666', 'license_expires_on' => now()->addDays(10)->toDateString(),
            'license_image_path' => 'x', 'vehicle_type' => 'tricycle', 'plate_number' => 'U7U778', 'or_cr_image_path' => 'y',
            'terms_accepted_at' => now(), 'terms_version' => RiderApplication::TERMS_VERSION, 'status' => 'approved',
        ]);

        return $rider;
    }

    private function readyShipment(): int
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-TEST-1', 'user_id' => $buyer->id, 'status' => 'ready', 'subtotal' => 500, 'shipping_fee' => 49,
            'total' => 549, 'payment_method' => 'cod', 'recipient_name' => 'Maria Santos', 'phone' => '09170000000',
            'shipping_address' => '12 Rizal St, Calamba, Laguna', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('shipments')->insertGetId([
            'order_id' => $orderId, 'tracking_number' => 'TRK-TEST', 'status' => 'ready', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_dashboard_shows_rider_details_and_hides_recipients_until_accepted(): void
    {
        $shipmentId = $this->readyShipment();
        Sanctum::actingAs($this->rider());

        $this->getJson('/api/mobile/rider/dashboard')
            ->assertOk()
            ->assertJsonPath('rider.vehicle_label', 'Tricycle')
            ->assertJsonPath('rider.plate_number', 'U7U778')
            ->assertJsonPath('rider.license_expiring_soon', true)
            ->assertJsonPath('stats.available', 1)
            ->assertJsonPath('available.0.id', $shipmentId)
            ->assertJsonPath('available.0.cash_to_collect', 549)
            ->assertJsonPath('available.0.recipient_name', null);
    }

    public function test_rider_accepts_and_delivers_and_earns_the_delivery_fee(): void
    {
        $shipmentId = $this->readyShipment();
        Sanctum::actingAs($this->rider());

        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/claim")
            ->assertOk()
            ->assertJsonPath('delivery.recipient_name', 'Maria Santos')
            ->assertJsonPath('delivery.next_status', 'picked_up');

        foreach (['picked_up', 'in_transit', 'delivered'] as $status) {
            $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => $status])->assertOk();
        }
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'picked_up'])->assertUnprocessable();

        $this->getJson('/api/mobile/rider/dashboard')
            ->assertJsonPath('stats.completed', 1)
            ->assertJsonPath('stats.earnings_today', 49)
            ->assertJsonCount(1, 'recent');
        $this->assertSame('completed', DB::table('orders')->value('status'));
    }

    public function test_a_delivery_taken_by_another_rider_cannot_be_claimed_or_updated(): void
    {
        $shipmentId = $this->readyShipment();
        $first = $this->rider();
        Sanctum::actingAs($first);
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/claim")->assertOk();

        $other = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        Sanctum::actingAs($other);
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/claim")->assertUnprocessable();
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'picked_up'])->assertForbidden();
    }

    public function test_buyers_and_inactive_riders_cannot_open_the_rider_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $this->getJson('/api/mobile/rider/dashboard')->assertForbidden()->assertJsonPath('code', 'not_rider');

        Sanctum::actingAs(User::factory()->create(['role' => 'rider', 'status' => 'pending']));
        $this->getJson('/api/mobile/rider/dashboard')->assertForbidden()->assertJsonPath('code', 'inactive');
    }
}
