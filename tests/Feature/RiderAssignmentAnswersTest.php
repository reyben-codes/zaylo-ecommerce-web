<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ParcelDeliveryUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Riders answer each assignment (accept, or decline with a reason so the partner can reassign it), report failed
 * attempts with a reason the buyer and seller are told, and see their delivered parcels and reports.
 */
class RiderAssignmentAnswersTest extends TestCase
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
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => $name]);
        Rider::create([
            'user_id' => $user->id, 'logistics_provider_id' => $this->partner->id, 'vehicle_type' => 'Motorcycle',
            'plate_no' => fake()->unique()->bothify('???####'), 'license_number' => fake()->unique()->bothify('S##-##-######'),
            'license_expires_on' => now()->addYear()->toDateString(), 'status' => 'approved', 'is_active' => true,
        ]);

        return $user;
    }

    private function parcel(string $status, ?User $rider = null, ?User $seller = null): int
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-T-'.fake()->unique()->numberBetween(100, 999), 'user_id' => $buyer->id, 'seller_id' => $seller?->id,
            'status' => 'in_transit', 'subtotal' => 500, 'shipping_fee' => 49, 'total' => 549, 'payment_method' => 'cod',
            'recipient_name' => 'Maria Santos', 'phone' => '09170000000', 'shipping_address' => '12 Rizal St, Calamba, Laguna',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert(['order_id' => $orderId, 'provider' => 'cod', 'status' => 'pending', 'amount' => 549, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('shipments')->insertGetId([
            'order_id' => $orderId, 'courier_id' => $rider?->id, 'tracking_number' => 'TRK-'.$orderId,
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function code(int $shipmentId): string
    {
        return DB::table('shipments')->where('id', $shipmentId)->value('tracking_number');
    }

    public function test_a_new_assignment_must_be_accepted_before_it_can_be_scanned(): void
    {
        $rider = $this->rider();
        $shipmentId = $this->parcel('assigned', $rider);
        $this->signInMobile($rider);

        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $this->code($shipmentId)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Accept this delivery first, then scan it again to start.');

        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/accept")
            ->assertOk()
            ->assertJsonPath('delivery.status', 'assigned')
            ->assertJsonPath('delivery.needs_acceptance', false)
            ->assertJsonPath('delivery.next_status', 'out_for_delivery');
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/accept")->assertUnprocessable();
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $this->code($shipmentId)])->assertOk()->assertJsonPath('step', 'out_for_delivery');
    }

    public function test_a_declined_parcel_goes_back_to_the_hub_with_the_reason_for_the_partner(): void
    {
        $rider = $this->rider();
        $shipmentId = $this->parcel('assigned', $rider);
        $this->signInMobile($rider);

        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/decline", ['reason' => 'other'])->assertJsonValidationErrors('note');
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/decline", ['reason' => 'nope'])->assertJsonValidationErrors('reason');
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/decline", ['reason' => 'vehicle', 'note' => 'Flat tire'])
            ->assertOk()
            ->assertJsonPath('message', 'Delivery declined. Your logistics partner will assign it to another rider.');

        $shipment = DB::table('shipments')->find($shipmentId);
        $this->assertNull($shipment->courier_id);
        $this->assertSame('in_transit', $shipment->status);
        $this->assertDatabaseHas('rider_delivery_logs', ['shipment_id' => $shipmentId, 'user_id' => $rider->id, 'action' => 'declined', 'reason' => 'vehicle', 'note' => 'Flat tire']);
        $this->getJson('/api/mobile/rider/dashboard')->assertJsonPath('stats.active', 0);
        // The buyer's order does not change: it is still on its way, waiting for another rider.
        $this->assertSame('in_transit', DB::table('orders')->value('status'));

        $this->actingAs($this->partner->owner)->get(route('logistics.assignments'))
            ->assertOk()->assertSee('Declined by Jun Reyes: Vehicle problem (Flat tire)');
    }

    public function test_a_parcel_cannot_be_declined_once_it_is_out_for_delivery(): void
    {
        $rider = $this->rider();
        $shipmentId = $this->parcel('out_for_delivery', $rider);
        $this->signInMobile($rider);

        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/decline", ['reason' => 'too_far'])->assertJsonValidationErrors('shipment');
        $this->postJson("/api/mobile/rider/deliveries/{$this->parcel('assigned', $this->rider('Other Rider'))}/decline", ['reason' => 'too_far'])->assertForbidden();
    }

    public function test_a_buyer_cancelling_at_the_door_is_reported_with_the_reason_to_buyer_and_seller(): void
    {
        Notification::fake();
        $rider = $this->rider();
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $shipmentId = $this->parcel('out_for_delivery', $rider, $seller);
        $buyer = User::find(DB::table('orders')->value('user_id'));
        $this->signInMobile($rider);

        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'failed', 'reason' => 'other'])->assertJsonValidationErrors('note');
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'failed', 'reason' => 'buyer_cancelled', 'note' => 'Changed their mind'])
            ->assertOk()
            ->assertJsonPath('delivery.status', 'failed')
            ->assertJsonPath('delivery.last_failure.reason', 'Buyer cancelled the order at delivery')
            ->assertJsonPath('delivery.last_failure.note', 'Changed their mind');

        $reason = 'Buyer cancelled the order at delivery (Changed their mind)';
        Notification::assertSentTo($buyer, ParcelDeliveryUpdate::class, fn ($n) => $n->step === 'failed' && ! $n->forSeller && $n->reason === $reason);
        Notification::assertSentTo($seller, ParcelDeliveryUpdate::class, fn ($n) => $n->step === 'failed' && $n->forSeller && $n->reason === $reason);
        $this->assertDatabaseHas('order_status_histories', ['status' => 'failed', 'note' => "Delivery attempt failed: {$reason}."]);
        // The partner returns the parcel; the rider can no longer take it out once it is back with the seller.
        $this->assertSame('in_transit', DB::table('orders')->value('status'));
    }

    public function test_only_the_rider_can_mark_a_parcel_delivered(): void
    {
        Notification::fake();
        $rider = $this->rider();
        $shipmentId = $this->parcel('out_for_delivery', $rider);
        $buyer = User::find(DB::table('orders')->value('user_id'));

        $this->actingAs($this->partner->owner)
            ->patch(route('logistics.parcels.advance', $shipmentId), ['status' => 'delivered'])
            ->assertSessionHasErrors('status');
        $this->assertSame('out_for_delivery', DB::table('shipments')->value('status'));

        $this->signInMobile($rider);
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $this->code($shipmentId)])->assertOk()->assertJsonPath('step', 'delivered');
        Notification::assertSentTo($buyer, ParcelDeliveryUpdate::class, fn ($n) => $n->step === 'delivered');
        $this->assertSame('completed', DB::table('orders')->value('status'));
    }

    public function test_a_delivered_parcel_shows_everything_the_rider_did_with_it(): void
    {
        $rider = $this->rider();
        $shipmentId = $this->parcel('assigned', $rider);
        $this->signInMobile($rider);
        $this->postJson("/api/mobile/rider/deliveries/$shipmentId/accept")->assertOk();
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $this->code($shipmentId)])->assertOk();
        $this->patchJson("/api/mobile/rider/deliveries/$shipmentId", ['status' => 'failed', 'reason' => 'unreachable'])->assertOk();
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $this->code($shipmentId)])->assertOk();
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $this->code($shipmentId)])->assertOk()->assertJsonPath('step', 'delivered');

        $this->getJson("/api/mobile/rider/deliveries/$shipmentId")
            ->assertOk()
            ->assertJsonPath('delivery.status', 'delivered')
            ->assertJsonPath('delivery.recipient_name', 'Maria Santos')
            ->assertJsonCount(5, 'delivery.timeline')
            ->assertJsonPath('delivery.timeline.0.label', 'You accepted the delivery')
            ->assertJsonPath('delivery.timeline.2.reason', 'Could not reach the buyer by phone')
            ->assertJsonPath('delivery.timeline.4.label', 'Delivered to the buyer');
        $this->getJson('/api/mobile/rider/history')->assertJsonPath('deliveries.0.id', $shipmentId);

        $this->getJson('/api/mobile/rider/deliveries/'.$this->parcel('assigned', $this->rider('Other Rider')))->assertNotFound();
    }

    public function test_reports_count_answers_deliveries_failures_and_money(): void
    {
        $rider = $this->rider();
        $delivered = $this->parcel('assigned', $rider);
        $declined = $this->parcel('assigned', $rider);
        $failed = $this->parcel('out_for_delivery', $rider);
        $this->signInMobile($rider);

        $this->postJson("/api/mobile/rider/deliveries/$delivered/accept")->assertOk();
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $this->code($delivered)])->assertOk();
        $this->postJson('/api/mobile/rider/deliveries/scan', ['code' => $this->code($delivered)])->assertOk();
        $this->postJson("/api/mobile/rider/deliveries/$declined/decline", ['reason' => 'too_far'])->assertOk();
        $this->patchJson("/api/mobile/rider/deliveries/$failed", ['status' => 'failed', 'reason' => 'buyer_refused'])->assertOk();

        $this->getJson('/api/mobile/rider/reports?period=today')
            ->assertOk()
            ->assertJsonPath('period', 'today')
            ->assertJsonPath('assignments.accepted', 1)
            ->assertJsonPath('assignments.declined', 1)
            ->assertJsonPath('deliveries.delivered', 1)
            ->assertJsonPath('deliveries.failed_attempts', 1)
            ->assertJsonPath('deliveries.success_rate', 50)
            ->assertJsonPath('money.cash_collected', 549)
            ->assertJsonPath('money.delivery_fees', 49)
            ->assertJsonPath('failed_reasons.0.label', 'Buyer refused to accept the parcel')
            ->assertJsonPath('declined_reasons.0.label', 'Outside my delivery area')
            ->assertJsonCount(7, 'last_7_days')
            ->assertJsonPath('last_7_days.6.delivered', 1)
            ->assertJsonPath('last_7_days.6.failed', 1);
        $this->getJson('/api/mobile/rider/reports?period=year')->assertJsonValidationErrors('period');
        $this->assertSame(0, Shipment::where('courier_id', $rider->id)->where('id', $declined)->count());
    }
}
