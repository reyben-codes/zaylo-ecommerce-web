<?php

namespace Tests\Feature;

use App\Models\LogisticsMessage;
use App\Models\LogisticsProvider;
use App\Models\LogisticsThread;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The logistics partner's sorting center: pickup requests, hub sorting, rider assignment by area, monitoring,
 * reports and chat, only with the partner's own riders and parcels.
 */
class LogisticsOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private LogisticsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->owner, $this->provider] = $this->partner('Express Delivery');
    }

    public function test_sorting_center_pages_are_part_of_the_logistics_portal(): void
    {
        $this->parcel('ZX-0001', 'ready');
        $this->actingAs($this->owner);

        $dashboard = $this->get(route('logistics.dashboard'))->assertOk()->assertSee('Pickup requests')->assertSee('Sorting center');
        foreach (['pickups', 'incoming', 'sorting', 'scan', 'assignments', 'deliveries', 'monitoring', 'riders', 'messages', 'reports', 'account'] as $page) {
            $dashboard->assertSee(route("logistics.{$page}"), false);
            $this->get(route("logistics.{$page}"))->assertOk();
        }
        $this->get(route('logistics.pickups'))->assertSee('ZX-0001')->assertSee('Confirm pickup')->assertSee('Decline');
        $this->get('/sorting-center/dashboard')->assertNotFound();

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('logistics.pickups'))->assertForbidden();
    }

    public function test_a_parcel_moves_from_pickup_request_to_delivery(): void
    {
        $rider = $this->rider($this->provider, 'Marco Rider');
        $shipment = $this->parcel('ZX-0002', 'ready');
        $this->actingAs($this->owner);

        // Pickup must be confirmed before it can be collected.
        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'picked_up'])->assertSessionHasErrors('status');
        $this->patch(route('logistics.parcels.pickup', $shipment), ['decision' => 'confirm', 'pickup_date' => now('Asia/Manila')->subDay()->toDateString()])->assertSessionHasErrors('pickup_date');
        $this->patch(route('logistics.parcels.pickup', $shipment), ['decision' => 'confirm', 'pickup_date' => now('Asia/Manila')->addDay()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', ShipmentWorkflow::stageOf($shipment->fresh()));
        $this->get(route('logistics.pickups', ['tab' => 'scheduled']))->assertSee('ZX-0002')->assertSee('Mark picked up');
        $sellerThread = LogisticsThread::where('user_id', $shipment->sellerOrder->seller->user_id)->firstOrFail();
        $this->assertStringContainsString('Pickup confirmed for order ZAY-ZX-0002', $sellerThread->messages()->value('body'));

        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'picked_up'])->assertSessionHasNoErrors();
        $this->assertSame('picked_up', $shipment->fresh()->status);
        $this->assertSame('shipped', $shipment->sellerOrder->fresh()->status);
        $this->get(route('logistics.incoming'))->assertSee('ZX-0002')->assertSee('Receive at hub');

        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'in_transit'])->assertSessionHasNoErrors();
        // Received parcels are sorted before a rider can take them; the area is suggested from the address.
        $this->get(route('logistics.sorting'))->assertSee('ZX-0002')->assertSee('value="Pasig City, Metro Manila"', false);
        $this->post(route('logistics.parcels.assign', $shipment), ['rider_id' => $rider->id])->assertSessionHasErrors('shipment');
        $this->patch(route('logistics.parcels.sort', $shipment), ['area' => '  Pasig City,  Metro Manila '])->assertSessionHasNoErrors();
        $this->assertSame('Pasig City, Metro Manila', $shipment->fresh()->sort_area);
        $this->get(route('logistics.assignments'))->assertSee('Pasig City, Metro Manila')->assertSee('Assign all')->assertSee('ZX-0002');

        $this->post(route('logistics.parcels.assign', $shipment), ['rider_id' => $rider->id])->assertSessionHasNoErrors();
        $this->assertSame('assigned', $shipment->fresh()->status);
        $this->assertSame($rider->id, $shipment->fresh()->rider_id);
        $this->assertFalse($this->get(route('logistics.assignments'))->viewData('shipments')->contains('id', $shipment->id));
        $this->get(route('logistics.monitoring'))->assertSee('ZX-0002')->assertSee('Marco Rider');

        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'out_for_delivery'])->assertSessionHasNoErrors();
        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'failed'])->assertSessionHasNoErrors();
        $this->get(route('logistics.monitoring'))->assertViewHas('attention', fn ($parcels) => $parcels->contains('id', $shipment->id));
        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'out_for_delivery'])->assertSessionHasNoErrors();
        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'delivered'])->assertSessionHasNoErrors();

        $fresh = $shipment->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertTrue((bool) $fresh->cod_collected);
        $this->assertSame(2, (int) $fresh->attempts);
        $this->assertSame('delivered', $shipment->sellerOrder->fresh()->status);
        // A finished parcel cannot be moved again.
        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'out_for_delivery'])->assertSessionHasErrors('status');

        $this->get(route('logistics.reports'))->assertOk()->assertSee('100% success rate')
            ->assertViewHas('byRider', fn ($rows) => $rows->first()['name'] === 'Marco Rider' && $rows->first()['delivered'] === 1)
            ->assertViewHas('byArea', fn ($rows) => $rows->first()['name'] === 'Pasig City, Metro Manila');
        $pdf = $this->get(route('logistics.reports.export'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('.pdf', $pdf->headers->get('Content-Disposition'));
        $this->get(route('logistics.deliveries', ['stage' => 'delivered']))->assertSee('ZX-0002');
    }

    public function test_a_declined_pickup_goes_back_to_the_seller_with_the_reason(): void
    {
        $shipment = $this->parcel('ZX-0005', 'ready');
        $this->actingAs($this->owner);

        $this->patch(route('logistics.parcels.pickup', $shipment), ['decision' => 'decline'])->assertSessionHasErrors('reason');
        $this->patch(route('logistics.parcels.pickup', $shipment), ['decision' => 'decline', 'reason' => 'The address is outside our service area.'])->assertSessionHasNoErrors();

        $this->assertNull(Shipment::find($shipment->id));
        $this->assertSame('packed', $shipment->sellerOrder->fresh()->status);
        $sellerUser = User::find($shipment->sellerOrder->seller->user_id);
        $this->actingAs($sellerUser)->get(route('seller.logistics-messages'))->assertOk()->assertSee('Express Delivery');
        $thread = LogisticsThread::where('user_id', $sellerUser->id)->firstOrFail();
        $this->actingAs($sellerUser)->get(route('seller.logistics-messages', ['thread' => $thread->id]))
            ->assertSee('outside our service area');
    }

    public function test_a_whole_area_goes_to_one_rider(): void
    {
        $rider = $this->rider($this->provider, 'Area Rider');
        $first = $this->parcel('ZX-0006', 'sorted', area: 'Pasig City, Metro Manila');
        $second = $this->parcel('ZX-0007', 'sorted', area: 'Pasig City, Metro Manila');
        $elsewhere = $this->parcel('ZX-0008', 'sorted', area: 'Quezon City, Metro Manila');
        $this->actingAs($this->owner);

        $this->post(route('logistics.areas.assign'), ['area' => 'Pasig City, Metro Manila', 'rider_id' => $rider->id])->assertSessionHasNoErrors();

        $this->assertSame([$rider->id, $rider->id, null], [$first->fresh()->rider_id, $second->fresh()->rider_id, $elsewhere->fresh()->rider_id]);
        $this->assertSame('assigned', $first->fresh()->status);
        $this->post(route('logistics.areas.assign'), ['area' => 'Pasig City, Metro Manila', 'rider_id' => $rider->id])->assertSessionHasErrors('area');
        $this->get(route('logistics.assignments'))->assertViewHas('workload', fn ($rows) => $rows->firstWhere('rider.id', $rider->id)['assigned'] === 2);
    }

    public function test_scanning_finds_a_parcel_and_offers_its_next_step(): void
    {
        $this->parcel('ZX-0003', 'picked_up');
        $this->actingAs($this->owner);

        $this->get(route('logistics.scan', ['tracking' => 'ZX-0003']))->assertOk()->assertSee('ZX-0003')->assertSee('Picked up from seller')->assertSee('Receive at hub');
        $this->get(route('logistics.scan', ['tracking' => 'NOPE-404']))->assertOk()->assertSee('No parcel with tracking number');
    }

    public function test_only_this_partners_available_riders_and_parcels_are_used(): void
    {
        [, $otherProvider] = $this->partner('Agila Cargo');
        $theirRider = $this->rider($otherProvider, 'Agila Rider');
        $pausedRider = $this->rider($this->provider, 'Paused Rider');
        $theirParcel = $this->parcel('AG-0001', 'sorted', $otherProvider);
        $ourParcel = $this->parcel('ZX-0004', 'sorted');
        $this->actingAs($this->owner);

        $this->patch(route('logistics.riders.availability', $pausedRider), ['available' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($pausedRider->fresh()->is_active);
        $this->get(route('logistics.riders', ['status' => 'approved']))->assertSee('Paused: no new deliveries');

        $this->get(route('logistics.assignments'))->assertSee('ZX-0004')->assertDontSee('AG-0001')->assertDontSee('Agila Rider');
        $this->post(route('logistics.parcels.assign', $ourParcel), ['rider_id' => $pausedRider->id])->assertSessionHasErrors('rider_id');
        $this->post(route('logistics.parcels.assign', $ourParcel), ['rider_id' => $theirRider->id])->assertSessionHasErrors('rider_id');
        $this->post(route('logistics.parcels.assign', $theirParcel), ['rider_id' => $pausedRider->id])->assertForbidden();
        $this->patch(route('logistics.parcels.advance', $theirParcel), ['status' => 'picked_up'])->assertForbidden();
        $this->patch(route('logistics.parcels.sort', $theirParcel), ['area' => 'Anywhere'])->assertForbidden();
        $this->patch(route('logistics.riders.availability', $theirRider), ['available' => 0])->assertForbidden();
        $this->assertSame('in_transit', $ourParcel->fresh()->status);
    }

    public function test_logistics_sellers_and_riders_can_message_each_other(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Nina Seller']);
        $rider = $this->rider($this->provider, 'Chat Rider');
        $this->actingAs($this->owner);

        // The partner starts a conversation with a seller and sends a message.
        $this->post(route('logistics.messages.start'), ['participant_id' => $seller->id])->assertRedirect();
        $thread = LogisticsThread::where('user_id', $seller->id)->firstOrFail();
        $this->postJson(route('logistics-chat.send', $thread), ['body' => 'Hi Nina, pickup is at 2 PM.'])->assertCreated()->assertJsonPath('message.mine', true);
        $this->postJson(route('logistics-chat.send', $thread), ['body' => ' '])->assertUnprocessable();

        // The seller sees it, replies, and the partner receives the reply.
        $this->actingAs($seller)->get(route('seller.logistics-messages', ['thread' => $thread->id]))->assertOk()->assertSee('pickup is at 2 PM');
        $this->assertNotNull(LogisticsMessage::where('sender_id', $this->owner->id)->value('read_at'));
        $this->actingAs($seller)->post(route('logistics-chat.send', $thread), ['body' => 'Noted, thanks!'])->assertRedirect();
        $first = LogisticsMessage::where('sender_id', $this->owner->id)->value('id');
        $this->actingAs($this->owner)->getJson(route('logistics-chat.messages', ['thread' => $thread, 'after' => $first]))
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Noted, thanks!');

        // Sellers can also open a conversation with an approved partner.
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($otherSeller)->post(route('seller.logistics-messages.start'), ['provider_id' => $this->provider->id])->assertRedirect();
        $this->assertTrue(LogisticsThread::where('user_id', $otherSeller->id)->where('logistics_provider_id', $this->provider->id)->exists());

        // Nobody else can read or write in someone else's conversation.
        $this->actingAs($otherSeller)->getJson(route('logistics-chat.messages', $thread))->assertForbidden();
        $this->actingAs($otherSeller)->postJson(route('logistics-chat.send', $thread), ['body' => 'Hello?'])->assertForbidden();
        [$otherOwner] = $this->partner('Agila Cargo');
        $this->actingAs($otherOwner)->getJson(route('logistics-chat.messages', $thread))->assertForbidden();

        // A rider chats with their own partner from the rider portal.
        $this->actingAs($rider->user)->get(route('courier.chat'))->assertOk()->assertSee('Express Delivery');
        $riderThread = LogisticsThread::where('user_id', $rider->user_id)->firstOrFail();
        $this->actingAs($rider->user)->postJson(route('logistics-chat.send', $riderThread), ['body' => 'On my way to the hub.'])->assertCreated();
        $this->actingAs($this->owner)->get(route('logistics.messages', ['thread' => $riderThread->id]))->assertSee('On my way to the hub.');
        // Partners can only start conversations with sellers and their own riders.
        $this->actingAs($this->owner)->post(route('logistics.messages.start'), ['participant_id' => $otherOwner->id])->assertStatus(422);
    }

    private function partner(string $name): array
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved']);

        return [$owner, $provider];
    }

    private function rider(LogisticsProvider $provider, string $name): Rider
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => $name]);

        return Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);
    }

    private function parcel(string $tracking, string $stage, ?LogisticsProvider $provider = null, string $area = 'Pasig City, Metro Manila'): Shipment
    {
        $provider ??= $this->provider;
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active'])->sellers()->create(['name' => 'Northline Goods '.$tracking, 'slug' => 'northline-'.$tracking, 'status' => 'approved']);
        $order = Order::create([
            'buyer_id' => User::factory()->create(['role' => 'buyer'])->id, 'reference' => 'ZAY-'.$tracking, 'total_minor' => 10000,
            'payment_method' => 'cod', 'shipping_address' => ['recipient' => 'Juan Dela Cruz', 'city' => 'Pasig City', 'province' => 'Metro Manila'],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'ready_to_ship',
        ]);

        return Shipment::create([
            'seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'tracking_code' => $tracking,
            'status' => ShipmentWorkflow::stored($stage),
        ] + ($stage === 'sorted' ? ['sorted_at' => now(), 'sort_area' => $area] : []));
    }
}
