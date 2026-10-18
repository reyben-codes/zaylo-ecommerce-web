<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\LegacyOrderTables;
use Tests\TestCase;

/**
 * The rider app follows the logistics workflow: the logistics partner assigns
 * parcels, and the rider takes them out for delivery and reports the result.
 */
class MobileRiderDashboardTest extends TestCase
{
    use LegacyOrderTables;
    use RefreshDatabase;

    private LogisticsProvider $partner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
        config(['marketplace.seller_managed_delivery' => false]);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $this->partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
    }

    private function rider(string $name = 'Jun Reyes'): User
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => $name, 'phone' => fake()->unique()->numerify('09#########')]);
        Rider::create([
            'user_id' => $user->id, 'logistics_provider_id' => $this->partner->id, 'vehicle_type' => 'Motorcycle',
            'plate_no' => 'NBC1234', 'license_number' => 'S33-45-666666', 'license_expires_on' => now()->addDays(10)->toDateString(),
            'status' => 'approved', 'is_active' => true,
        ]);

        return $user;
    }

    /** A parcel at [$status]; assigned to [$rider] when given (the deployed schema assigns through courier_id). */
    private function parcel(string $status, ?User $rider = null): int
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-TEST-'.fake()->unique()->numberBetween(100, 999), 'user_id' => $buyer->id, 'status' => 'in_transit',
            'subtotal' => 500, 'shipping_fee' => 49, 'total' => 549, 'payment_method' => 'cod', 'recipient_name' => 'Maria Santos',
            'phone' => '09170000000', 'shipping_address' => '12 Rizal St, Calamba, Laguna', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert(['order_id' => $orderId, 'provider' => 'cod', 'status' => 'pending', 'amount' => 549, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('shipments')->insertGetId([
            'order_id' => $orderId, 'courier_id' => $rider?->id, 'tracking_number' => 'TRK-'.$orderId,
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_dashboard_shows_the_riders_partner_vehicle_and_assigned_parcels_only(): void
    {
        $rider = $this->rider();
        $mine = $this->parcel('assigned', $rider);
        $this->parcel('assigned', $this->rider('Other Rider'));
        $this->parcel('in_transit'); // at the hub, not assigned yet
        Sanctum::actingAs($rider);

        $this->getJson('/api/mobile/rider/dashboard')
            ->assertOk()
            ->assertJsonPath('delivery_mode', 'logistics')
            ->assertJsonPath('rider.logistics_partner', 'Express Delivery')
            ->assertJsonPath('rider.vehicle_label', 'Motorcycle')
            ->assertJsonPath('rider.plate_number', 'NBC1234')
            ->assertJsonPath('rider.license_expiring_soon', true)
            ->assertJsonPath('stats.available', 0)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonCount(0, 'available')
            ->assertJsonPath('active.0.id', $mine)
            ->assertJsonPath('active.0.status_label', 'Rider assigned')
            ->assertJsonPath('active.0.recipient_name', 'Maria Santos')
            ->assertJsonPath('active.0.next_status', 'out_for_delivery')
            ->assertJsonPath('active.0.chat_open', true);
    }

    public function test_dashboard_carries_announcements_for_riders_only(): void
    {
        $rider = $this->rider();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Announcement::create(['title' => 'Rider meeting', 'body' => 'Friday at 9.', 'audience' => 'rider', 'created_by' => $admin->id]);
        Announcement::create(['title' => 'Buyer sale', 'body' => 'Shop now.', 'audience' => 'buyer', 'created_by' => $admin->id]);
        Sanctum::actingAs($rider);

        $this->getJson('/api/mobile/rider/dashboard')
            ->assertJsonCount(1, 'announcements')
            ->assertJsonPath('announcements.0.title', 'Rider meeting');
    }

    public function test_rider_delivers_a_parcel_and_earns_the_delivery_fee(): void
    {
        $rider = $this->rider();
        $shipmentId = $this->parcel('assigned', $rider);
        Sanctum::actingAs($rider);

        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'delivered'])->assertUnprocessable();
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'out_for_delivery'])
            ->assertOk()
            ->assertJsonPath('delivery.status', 'out_for_delivery')
            ->assertJsonPath('delivery.actions.0.status', 'delivered')
            ->assertJsonPath('delivery.actions.1.status', 'failed');
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'delivered'])->assertOk();

        $this->getJson('/api/mobile/rider/dashboard')
            ->assertJsonPath('stats.completed', 1)
            ->assertJsonPath('stats.earnings_today', 49)
            ->assertJsonCount(1, 'recent')
            ->assertJsonPath('recent.0.chat_open', false);
        $this->assertSame('completed', DB::table('orders')->value('status'));
        $this->assertSame('paid', DB::table('payments')->value('status'));
    }

    public function test_a_failed_attempt_can_be_retried(): void
    {
        $rider = $this->rider();
        $shipmentId = $this->parcel('out_for_delivery', $rider);
        Sanctum::actingAs($rider);

        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'failed'])
            ->assertOk()
            ->assertJsonPath('delivery.status', 'failed')
            ->assertJsonPath('delivery.next_status', 'out_for_delivery');
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'out_for_delivery'])->assertOk();
    }

    public function test_riders_cannot_update_parcels_of_other_riders_or_claim_parcels(): void
    {
        $shipmentId = $this->parcel('assigned', $this->rider('Other Rider'));
        Sanctum::actingAs($this->rider());

        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'out_for_delivery'])->assertForbidden();
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/claim")->assertNotFound();
    }

    public function test_seller_managed_delivery_turns_rider_updates_off(): void
    {
        config(['marketplace.seller_managed_delivery' => true]);
        $rider = $this->rider();
        $shipmentId = $this->parcel('assigned', $rider);
        Sanctum::actingAs($rider);

        $this->getJson('/api/mobile/rider/dashboard')
            ->assertJsonPath('delivery_mode', 'seller_managed')
            ->assertJsonPath('active.0.next_status', null);
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'out_for_delivery'])->assertStatus(409);
    }
}
