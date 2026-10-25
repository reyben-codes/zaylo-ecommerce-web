<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * On the deployed schema: the seller picks the logistics partner when an order is ready to ship, only that partner
 * sees the pickup, open requests go to the first partner to confirm, the seller sees the pickup date, and collecting
 * the parcel needs its waybill code.
 */
class PickupPartnerTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareDeployedSchema();
    }

    public function test_the_migration_records_the_partner_and_gives_old_parcels_to_the_only_partner(): void
    {
        $this->assertFalse(Schema::hasColumn('shipments', 'logistics_provider_id'));
        [, $express] = $this->partner('Express Delivery');
        $order = $this->order($this->buyer(), $this->seller(), 'ready_for_pickup');
        $shipment = $order->shipment()->create(['tracking_number' => 'TRK-OLD-PARCEL', 'status' => 'ready']);

        $this->addPartnerColumn();
        $this->assertTrue(Schema::hasColumn('shipments', 'logistics_provider_id'));
        $this->assertSame($express->id, (int) $shipment->fresh()->logistics_provider_id);

        // Running it again changes nothing.
        $this->addPartnerColumn();
        $this->assertSame($express->id, (int) $shipment->fresh()->logistics_provider_id);
    }

    public function test_the_seller_picks_the_partner_and_only_that_partner_handles_the_pickup(): void
    {
        $this->addPartnerColumn();
        [$expressOwner] = $this->partner('Express Delivery');
        [$agilaOwner, $agila] = $this->partner('Agila Logistics');
        $seller = $this->seller();
        $order = $this->order($this->buyer(), $seller, 'processing');

        $this->actingAs($seller)->get(route('seller.orders'))->assertOk()
            ->assertSee('name="logistics_provider_id"', false)->assertSee('Agila Logistics')->assertSee('Express Delivery');
        $this->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup'])->assertSessionHasErrors('logistics_provider_id');
        $this->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup', 'logistics_provider_id' => 999])->assertSessionHasErrors('logistics_provider_id');
        $this->assertSame('processing', $order->fresh()->status);

        $this->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup', 'logistics_provider_id' => $agila->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Pickup requested from Agila Logistics. Print the waybill and attach it to the parcel.');
        $shipment = $order->fresh()->shipment;
        $tracking = $shipment->tracking_number;
        $this->assertSame($agila->id, (int) $shipment->logistics_provider_id);
        $this->get(route('seller.orders'))->assertSee('Waiting for Agila Logistics to set a pickup date');
        $this->get(route('seller.orders.waybill.print', $order))->assertOk()->assertSee('Agila Logistics');

        // The other partner neither sees nor touches it.
        $this->actingAs($expressOwner)->get(route('logistics.pickups'))->assertOk()->assertDontSee($tracking);
        $this->patch(route('logistics.parcels.pickup', $shipment), ['decision' => 'confirm', 'pickup_date' => $this->tomorrow()])->assertForbidden();

        $this->actingAs($agilaOwner)->get(route('logistics.pickups'))->assertOk()->assertSee($tracking);
        $this->patch(route('logistics.parcels.pickup', $shipment), ['decision' => 'confirm', 'pickup_date' => $this->tomorrow()])->assertSessionHasNoErrors();
        $this->actingAs($seller)->get(route('seller.orders'))->assertSee('Pickup tomorrow · Agila Logistics');

        // Collecting it needs the waybill code: typed on the pickup list, or scanned on the Scan parcel page.
        $this->actingAs($agilaOwner)->get(route('logistics.pickups', ['tab' => 'scheduled']))->assertSee('Scan waybill code');
        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'picked_up', 'parcel' => $shipment->id, 'tracking' => 'TRK-NOT-THIS-ONE'])
            ->assertSessionHasErrors(['tracking' => "That code does not match parcel {$tracking}. Check that you have the right parcel."]);
        $this->get(route('logistics.scan', ['tracking' => $tracking]))->assertOk()
            ->assertSee('<input type="hidden" name="tracking" value="'.$tracking.'">', false)
            ->assertSee('data-camera-scan', false);
        $this->patch(route('logistics.parcels.advance', $shipment), ['status' => 'picked_up', 'tracking' => $tracking])->assertSessionHasNoErrors();
        $this->assertSame('picked_up', $shipment->fresh()->status);
        $this->actingAs($seller)->get(route('seller.orders'))->assertSee('Collected')->assertSee('by Agila Logistics');
    }

    public function test_a_single_partner_is_chosen_for_the_seller_and_open_requests_go_to_the_first_to_confirm(): void
    {
        $this->addPartnerColumn();
        $seller = $this->seller();
        $buyer = $this->buyer();

        // No partner yet: the request names none and stays open.
        $early = $this->order($buyer, $seller, 'processing');
        $this->actingAs($seller)->patch(route('seller.orders.status', $early), ['status' => 'ready_for_pickup'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Pickup requested. Print the waybill and attach it to the parcel.');
        $open = $early->fresh()->shipment;
        $this->assertNull($open->logistics_provider_id);
        $this->get(route('seller.orders'))->assertSee('Waiting for a logistics partner to set a pickup date');

        // One partner: nothing to choose.
        [$expressOwner, $express] = $this->partner('Express Delivery');
        $order = $this->order($buyer, $seller, 'processing');
        $this->get(route('seller.orders'))->assertDontSee('name="logistics_provider_id" class', false)->assertSee('request pickup from Express Delivery');
        $this->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup'])->assertSessionHasNoErrors();
        $this->assertSame($express->id, (int) $order->fresh()->shipment->logistics_provider_id);

        // The open request is visible to every partner until one confirms it.
        [$agilaOwner, $agila] = $this->partner('Agila Logistics');
        $this->actingAs($expressOwner)->get(route('logistics.pickups'))->assertSee($open->tracking_number);
        $this->actingAs($agilaOwner)->get(route('logistics.pickups'))->assertSee($open->tracking_number);
        $this->patch(route('logistics.parcels.pickup', $open), ['decision' => 'confirm', 'pickup_date' => $this->tomorrow()])->assertSessionHasNoErrors();
        $this->assertSame($agila->id, (int) $open->fresh()->logistics_provider_id);

        // (Clear the "pickup confirmed" message the test session would otherwise carry into the next page.)
        $this->flushSession();
        $this->actingAs($expressOwner)->get(route('logistics.pickups'))->assertDontSee($open->tracking_number);
        $this->patch(route('logistics.parcels.pickup', $open), ['decision' => 'confirm', 'pickup_date' => $this->tomorrow()])->assertForbidden();
    }

    private function addPartnerColumn(): void
    {
        (require database_path('migrations/2026_10_24_000001_add_logistics_provider_to_shipments.php'))->up();
    }

    private function tomorrow(): string
    {
        return now('Asia/Manila')->addDay()->toDateString();
    }

    private function partner(string $name): array
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved']);

        return [$owner, $provider];
    }

    private function seller(): User
    {
        return User::factory()->create(['role' => 'seller', 'status' => 'active']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['role' => 'buyer', 'status' => 'active']);
    }

    private function order(User $buyer, User $seller, string $status): Order
    {
        return Order::create([
            'buyer_id' => $buyer->id,
            'reference' => 'NORMALIZED-'.uniqid(),
            'total_minor' => 30000,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'order_number' => 'ZAY-'.strtoupper(uniqid()),
            'user_id' => $buyer->id,
            'seller_id' => $seller->sellerShop()->id,
            'status' => $status,
            'subtotal' => 250,
            'shipping_fee' => 50,
            'total' => 300,
            'currency' => 'PHP',
            'recipient_name' => $buyer->name,
            'phone' => '09171234567',
            'shipping_address' => 'Test Street, Manila',
            'placed_at' => now(),
        ]);
    }

    /** The live database's layout: orders carry the seller, and shipments have the hub steps but no partner yet. */
    private function prepareDeployedSchema(): void
    {
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('shipments');

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tracking_number')->unique();
            $table->string('status')->default('ready');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->timestamp('pickup_confirmed_at')->nullable();
            $table->date('pickup_scheduled_for')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->string('sort_area', 120)->nullable();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number')->nullable()->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('status')->default('placed');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->string('recipient_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('shipping_discount', 12, 2)->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable();
        });
    }
}
