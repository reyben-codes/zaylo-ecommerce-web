<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerDeliveryHandoverTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareLegacyOrderSchema();
    }

    public function test_ready_to_ship_opens_the_parcel_and_leaves_delivery_to_the_rider(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = $this->order($buyer, $seller, 'processing');

        $this->actingAs($seller)
            ->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'status' => 'ready']);

        $this->get(route('seller.handover'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('class="seller-sidebar__link is-active"', false)
            ->assertSee('Your logistics partner handles delivery')
            ->assertDontSee('Mark as shipped')
            ->assertDontSee('Temporary self-delivery workflow');
    }

    public function test_seller_cannot_take_delivery_steps_or_update_another_sellers_order(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = $this->order($buyer, $seller, 'ready_for_pickup');

        foreach (['picked_up', 'in_transit', 'out_for_delivery', 'completed'] as $status) {
            $this->actingAs($seller)
                ->patch(route('seller.orders.status', $order), ['status' => $status])
                ->assertSessionHasErrors('status');
        }
        $this->assertSame('ready_for_pickup', $order->fresh()->status);

        $this->actingAs($otherSeller)
            ->patch(route('seller.orders.status', $order), ['status' => 'cancelled'])
            ->assertForbidden();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
    }

    public function test_courier_can_claim_a_parcel_that_is_ready(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $order = $this->order($buyer, $seller, 'ready_for_pickup');
        $shipment = $order->shipment()->create([
            'tracking_number' => 'TRK-READY-PARCEL',
            'status' => 'ready',
        ]);

        $this->actingAs($rider)
            ->post(route('courier.deliveries.claim', $shipment))
            ->assertSessionHasNoErrors();

        $this->assertSame($rider->id, $shipment->fresh()->courier_id);
        $this->assertSame('assigned', $shipment->fresh()->status);
    }

    public function test_the_waybill_is_the_one_label_sellers_print_with_both_codes_for_the_rider(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = $this->order($buyer, $seller, 'ready_for_pickup');
        $order->shipment()->create(['tracking_number' => 'TRK-QR-LABEL', 'status' => 'ready']);

        $print = route('seller.orders.waybill.print', $order);
        $this->actingAs($seller)->get(route('seller.orders'))->assertOk()
            ->assertSee($print, false)->assertSee('Print waybill')->assertDontSee('Print label');
        $this->actingAs($seller)->get($print)->assertOk()
            ->assertSee('TRK-QR-LABEL')
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('data:image/png;base64,', false);
        $this->actingAs($otherSeller)->get($print)->assertForbidden();

        // The separate QR label page is gone.
        $this->actingAs($seller)->get(str_replace('/waybill/print', '/label', $print))->assertNotFound();
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
            // Checkout copies products.seller_id (the shop id) onto each order.
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

    private function prepareLegacyOrderSchema(): void
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
