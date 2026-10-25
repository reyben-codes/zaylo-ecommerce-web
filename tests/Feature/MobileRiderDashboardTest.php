<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\LogisticsMessage;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Notifications\ParcelDeliveryUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * The rider app follows the logistics workflow: the logistics partner assigns
 * parcels, and the rider takes them out for delivery and reports the result.
 */
class MobileRiderDashboardTest extends TestCase
{
    use SignsInMobile;
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
        $this->signInMobile($rider);

        $this->getJson('/api/mobile/rider/dashboard')
            ->assertOk()
            ->assertJsonPath('rider.logistics_partner', 'Express Delivery')
            ->assertJsonPath('rider.vehicle_label', 'Motorcycle')
            ->assertJsonPath('rider.plate_number', 'NBC1234')
            ->assertJsonPath('rider.license_expiring_soon', true)
            ->assertJsonPath('stats.available', 0)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonCount(0, 'available')
            ->assertJsonPath('active.0.id', $mine)
            ->assertJsonPath('stats.awaiting_acceptance', 1)
            ->assertJsonPath('active.0.status', 'new')
            ->assertJsonPath('active.0.status_label', 'New assignment')
            ->assertJsonPath('active.0.needs_acceptance', true)
            ->assertJsonPath('active.0.recipient_name', 'Maria Santos')
            ->assertJsonPath('active.0.next_status', null)
            ->assertJsonPath('active.0.chat_open', true)
            ->assertJsonPath('reasons.failed.0.code', 'buyer_cancelled')
            ->assertJsonPath('reasons.decline.0.code', 'too_far');
    }

    public function test_dashboard_carries_announcements_for_riders_only(): void
    {
        $rider = $this->rider();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Announcement::create(['title' => 'Rider meeting', 'body' => 'Friday at 9.', 'audience' => 'rider', 'created_by' => $admin->id]);
        Announcement::create(['title' => 'Buyer sale', 'body' => 'Shop now.', 'audience' => 'buyer', 'created_by' => $admin->id]);
        $this->signInMobile($rider);

        $this->getJson('/api/mobile/rider/dashboard')
            ->assertJsonCount(1, 'announcements')
            ->assertJsonPath('announcements.0.title', 'Rider meeting');
    }

    public function test_rider_delivers_a_parcel_and_earns_the_delivery_fee(): void
    {
        $rider = $this->rider();
        $shipmentId = $this->parcel('assigned', $rider);
        $orderId = DB::table('shipments')->where('id', $shipmentId)->value('order_id');
        $this->signInMobile($rider);

        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'delivered'])->assertUnprocessable();
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/accept")->assertOk()->assertJsonPath('delivery.next_status', 'out_for_delivery');
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => "TRK-$orderId"])
            ->assertOk()
            ->assertJsonPath('step', 'out_for_delivery')
            ->assertJsonPath('delivery.status', 'out_for_delivery')
            ->assertJsonPath('delivery.actions.0.status', 'delivered')
            ->assertJsonPath('delivery.actions.0.needs_scan', true)
            ->assertJsonPath('delivery.actions.1.status', 'failed')
            ->assertJsonPath('delivery.actions.1.needs_scan', false);
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => "TRK-$orderId"])->assertOk()->assertJsonPath('step', 'delivered');

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
        $this->signInMobile($rider);

        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'failed'])->assertJsonValidationErrors('reason');
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'failed', 'reason' => 'buyer_unavailable'])
            ->assertOk()
            ->assertJsonPath('delivery.status', 'failed')
            ->assertJsonPath('delivery.next_status', 'out_for_delivery');
        $code = DB::table('shipments')->where('id', $shipmentId)->value('tracking_number');
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $code])->assertOk()->assertJsonPath('step', 'out_for_delivery');
    }

    public function test_starting_and_finishing_a_delivery_need_a_scan(): void
    {
        $rider = $this->rider();
        $shipmentId = $this->parcel('assigned', $rider);
        $this->signInMobile($rider);

        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'out_for_delivery'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame('assigned', DB::table('shipments')->value('status'));
    }

    public function test_scanning_notifies_the_buyer_and_on_delivery_the_seller(): void
    {
        Notification::fake();
        $rider = $this->rider();
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $shipmentId = $this->parcel('assigned', $rider);
        DB::table('orders')->update(['seller_id' => $seller->id]);
        $buyer = User::find(DB::table('orders')->value('user_id'));
        $code = DB::table('shipments')->where('id', $shipmentId)->value('tracking_number');
        $this->signInMobile($rider);

        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/accept")->assertOk();
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => " $code "])->assertOk();
        Notification::assertSentTo($buyer, ParcelDeliveryUpdate::class, fn ($n) => $n->step === 'out_for_delivery' && ! $n->forSeller);
        Notification::assertNotSentTo($seller, ParcelDeliveryUpdate::class);
        $this->assertDatabaseHas('order_status_histories', ['status' => 'out_for_delivery']);

        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $code])->assertOk();
        Notification::assertSentTo($buyer, ParcelDeliveryUpdate::class, fn ($n) => $n->step === 'delivered');
        Notification::assertSentTo($seller, ParcelDeliveryUpdate::class, fn ($n) => $n->step === 'delivered' && $n->forSeller);
        $this->assertSame('completed', DB::table('orders')->value('status'));
        $this->assertTrue(LogisticsMessage::where('body', 'like', "%{$code}%delivered%")->exists());

        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'This parcel was already delivered.');
    }

    public function test_riders_cannot_scan_parcels_that_are_not_theirs(): void
    {
        $shipmentId = $this->parcel('assigned', $this->rider('Other Rider'));
        $code = DB::table('shipments')->where('id', $shipmentId)->value('tracking_number');
        $this->signInMobile($this->rider());

        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $code])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => 'TRK-NOPE'])->assertUnprocessable();
        $this->assertSame('assigned', DB::table('shipments')->value('status'));
    }

    public function test_riders_cannot_update_parcels_of_other_riders_or_claim_parcels(): void
    {
        $shipmentId = $this->parcel('assigned', $this->rider('Other Rider'));
        $this->signInMobile($this->rider());

        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'out_for_delivery'])->assertForbidden();
        // The logistics partner assigns parcels; riders cannot pick them up themselves.
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/claim")
            ->assertStatus(409)
            ->assertJsonPath('code', 'assigned_by_partner');
    }
}
