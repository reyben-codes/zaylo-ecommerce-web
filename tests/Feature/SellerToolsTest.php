<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Dispute;
use App\Models\Product;
use App\Models\User;
use App\Notifications\DisputeUpdated;
use App\Services\CheckoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\LegacyOrderTables;
use Tests\Support\PsgcFixtures;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Seller Center tools: order and return badges, live updates, the shop's ranking, handover confirmation, the rider's
 * delivery photo, returns and refunds, and flash sales.
 */
class SellerToolsTest extends TestCase
{
    use LegacyOrderTables;
    use PsgcFixtures;
    use RefreshDatabase;
    use SignsInMobile;

    private User $seller;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('delivery_events');
        $this->useLegacyOrderTables();
        // The deployed shipments table plus this release's columns.
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('delivery_proof_path')->nullable();
            $table->timestamp('handover_confirmed_at')->nullable();
        });
        // The deployed cart keeps the product beside its variation.
        Schema::table('cart_items', fn (Blueprint $table) => $table->unsignedBigInteger('product_id')->nullable());
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency')->nullable();
            $table->string('idempotency_key')->nullable();
        });
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Shop Owner']);
        $this->seller->sellerShop();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Maria Buyer']);
    }

    public function test_sidebar_counts_orders_to_prepare_and_returns_to_handle(): void
    {
        $this->order('placed');
        $this->order('processing');
        $this->order('completed');
        $this->returnCase($this->order('completed'), ['status' => 'open']);

        $this->actingAs($this->seller)->get(route('seller.dashboard'))->assertOk()
            ->assertSee('data-sidebar-count="orders" data-sidebar-count-label="to prepare" >2<span class="sr-only"> to prepare</span>', false)
            ->assertSee('data-sidebar-count="returns" data-sidebar-count-label="to handle" >1<span class="sr-only"> to handle</span>', false)
            ->assertSee('data-seller-updates-url="'.route('seller.updates').'"', false)
            ->assertSee('data-auto-refresh', false);

        $first = $this->getJson(route('seller.updates'))->assertOk()
            ->assertJsonPath('badges.orders', 2)
            ->assertJsonPath('badges.returns', 1)
            ->json('signature');

        // A new order changes the signature, so open pages refresh.
        $this->order('placed');
        $this->getJson(route('seller.updates'))->assertJsonPath('badges.orders', 3)
            ->assertJsonMissing(['signature' => $first]);
    }

    public function test_dashboard_shows_the_shop_ranking_and_best_selling_products(): void
    {
        $rival = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $rival->sellerShop();
        $shirt = $this->product('Linen Shirt');
        $cap = $this->product('Canvas Cap');
        $this->order('completed', subtotal: 900, items: [[$shirt, 3, 300]]);
        $this->order('placed', subtotal: 100, items: [[$cap, 1, 100]]);
        $this->order('cancelled', subtotal: 5000, items: [[$cap, 50, 100]]);
        $this->order('completed', subtotal: 400, seller: $rival);

        $this->actingAs($this->seller)->get(route('seller.dashboard'))->assertOk()
            ->assertSee('Best shop on ZAYLO')
            ->assertSee('Your shop has the most sales of all 2 shops.')
            ->assertSeeInOrder(['Linen Shirt', '3 sold', 'Best seller on ZAYLO', 'Canvas Cap', '1 sold', '#2 on ZAYLO']);

        $this->actingAs($rival)->get(route('seller.dashboard'))->assertOk()
            ->assertSee('#2 of 2 shops · Top 10');
    }

    public function test_seller_confirms_the_handover_after_the_partner_scans_the_parcel(): void
    {
        $order = $this->order('ready_for_pickup');
        $shipment = $order->shipment()->create(['tracking_number' => 'TRK-HANDOVER', 'status' => 'ready']);

        $this->actingAs($this->seller)->get(route('seller.orders'))->assertOk()->assertDontSee('Confirm handed over');
        $this->patch(route('seller.orders.handover', $order))->assertSessionHasErrors('handover');

        $shipment->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        $this->get(route('seller.handover'))->assertOk()->assertSee('Confirm handed over');
        $this->patch(route('seller.orders.handover', $order))->assertSessionHasNoErrors();
        $this->assertNotNull($shipment->fresh()->handover_confirmed_at);
        $this->get(route('seller.handover'))->assertSee('Handover confirmed')->assertDontSee('Confirm handed over');

        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($other)->patch(route('seller.orders.handover', $order))->assertForbidden();
    }

    public function test_rider_adds_a_delivery_photo_that_the_seller_and_buyer_can_open(): void
    {
        Storage::fake('local');
        $rider = $this->approvedRider();
        $order = $this->order('completed');
        $shipment = $order->shipment()->create([
            'tracking_number' => 'TRK-PROOF', 'status' => 'delivered', 'courier_id' => $rider->id, 'delivered_at' => now(),
        ]);

        $this->signInMobile($rider);
        $this->getJson("/api/mobile/rider/deliveries/{$shipment->id}")->assertOk()
            ->assertJsonPath('delivery.has_delivery_photo', false)
            ->assertJsonPath('delivery.can_add_delivery_photo', true);
        $this->post("/api/mobile/rider/deliveries/{$shipment->id}/proof", ['photo' => UploadedFile::fake()->image('door.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('delivery.has_delivery_photo', true);
        $path = $shipment->fresh()->delivery_proof_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->seller)->get(route('seller.orders'))->assertSee(route('delivery-proof', $shipment), false);
        $this->get(route('delivery-proof', $shipment))->assertOk();
        $this->actingAs($this->buyer)->get(route('delivery-proof', $shipment))->assertOk();
        $stranger = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($stranger)->get(route('delivery-proof', $shipment))->assertForbidden();

        // Another rider cannot add one, and a delivery from days ago is closed for photos.
        $this->signInMobile($this->approvedRider());
        $this->post("/api/mobile/rider/deliveries/{$shipment->id}/proof", ['photo' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])->assertForbidden();
        $shipment->update(['delivered_at' => now()->subDays(3)]);
        $this->signInMobile($rider);
        $this->post("/api/mobile/rider/deliveries/{$shipment->id}/proof", ['photo' => UploadedFile::fake()->image('late.jpg')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_seller_follows_a_return_confirms_it_and_records_the_refund(): void
    {
        Notification::fake();
        $order = $this->order('completed', subtotal: 450);
        $case = $this->returnCase($order, ['return_tracking_number' => 'RET-ABC123', 'return_method' => 'pickup', 'return_status' => 'scheduled']);

        $this->actingAs($this->seller)->get(route('seller.returns'))->assertOk()
            ->assertSee('Returns &amp; refunds', false)
            ->assertSee('RET-ABC123')
            ->assertDontSee('Return received</button>', false);
        // Not collected from the buyer yet.
        $this->patch(route('seller.returns.receive', $case))->assertSessionHasErrors('return');
        $this->patch(route('seller.returns.refund', $case), ['refund_amount' => 100])->assertSessionHasErrors('refund_amount');

        $case->update(['return_status' => 'in_transit']);
        $this->patch(route('seller.returns.receive', $case))->assertSessionHasNoErrors();
        $case->refresh();
        $this->assertSame('received_by_seller', $case->return_status);
        $this->assertNotNull($case->return_received_at);
        Notification::assertSentTo($this->buyer, DisputeUpdated::class, fn ($n) => $n->event === 'return_received');

        $this->patch(route('seller.returns.refund', $case), ['refund_amount' => 9999])->assertSessionHasErrors('refund_amount');
        $this->patch(route('seller.returns.refund', $case), ['refund_amount' => '450.00', 'refund_note' => 'Sent by GCash.'])->assertSessionHasNoErrors();
        $case->refresh();
        $this->assertSame('450.00', $case->refund_amount);
        $this->assertSame('refunded', $case->return_status);
        $this->assertSame('resolved', $case->status);
        $this->assertDatabaseHas('dispute_messages', ['dispute_id' => $case->id, 'sender_id' => $this->seller->id, 'body' => 'Refund of ₱450.00 issued. Sent by GCash.']);
        Notification::assertSentTo($this->buyer, DisputeUpdated::class, fn ($n) => $n->event === 'refunded');

        $this->get(route('seller.returns', ['status' => 'closed']))->assertSee('Refunded ₱450.00');
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($other)->patch(route('seller.returns.receive', $case))->assertForbidden();
    }

    public function test_flash_sale_is_short_limited_and_counted_at_checkout(): void
    {
        $product = $this->product('Flash Tee', price: 500, stock: 20);
        $this->actingAs($this->seller);
        $sale = fn (array $overrides = []) => $this->put(route('seller.products.sale', $product), $overrides + [
            'sale_kind' => 'flash', 'sale_type' => 'percent', 'sale_value' => 50, 'flash_quantity' => 3,
            'sale_starts_at' => now('Asia/Manila')->subHour()->format('Y-m-d\TH:i'),
            'sale_ends_at' => now('Asia/Manila')->addHours(5)->format('Y-m-d\TH:i'),
        ]);

        $sale(['sale_ends_at' => now('Asia/Manila')->addDays(2)->format('Y-m-d\TH:i')])->assertSessionHasErrors('sale_ends_at');
        $sale(['flash_quantity' => ''])->assertSessionHasErrors('flash_quantity');
        $sale()->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertTrue($product->isFlashSale());
        $this->assertSame('250.00', $product->price);
        $this->assertSame(3, $product->flashRemaining());

        $this->actingAs($this->buyer)->get(route('buyer.dashboard'))->assertOk()
            ->assertSee('Flash Sales')->assertSee('data-flash-countdown', false)
            ->assertSee('Flash sale · 3 left at this price');

        // Two units sell at the flash price; asking for two more than the one left is refused.
        $this->checkout($product, 2);
        $this->assertSame(1, $product->fresh()->flashRemaining());
        try {
            $this->checkout($product, 2);
            $this->fail('Buying more than the flash units left should be refused.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Only 1 of Flash Tee are left at the flash sale price', $error->getMessage());
        }
        $this->checkout($product, 1);
        $product->refresh();
        $this->assertFalse($product->isFlashSale());
        $this->assertSame('500.00', $product->price);
        $this->assertSame('Flash sale sold out', $product->sale_status);

        // A regular sale clears the flash settings.
        $this->actingAs($this->seller);
        $sale(['sale_kind' => 'regular', 'sale_ends_at' => now('Asia/Manila')->addDays(3)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertFalse($product->sale_is_flash);
        $this->assertNull($product->flash_quantity);
        $this->assertSame('250.00', $product->price);
    }

    private function product(string $name, float $price = 300, int $stock = 50): Product
    {
        return Product::create([
            'seller_id' => $this->seller->id, 'name' => $name, 'category' => 'clothing', 'gender' => 'unisex',
            'description' => 'A product for the seller tools tests.', 'price' => $price, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    /** @param list<array{0: Product, 1: int, 2: float}> $items */
    private function order(string $status, float $subtotal = 250, array $items = [], ?User $seller = null): \App\Models\Order
    {
        $order = \App\Models\Order::create([
            'order_number' => 'ZAY-'.Str::upper(Str::random(8)), 'user_id' => $this->buyer->id,
            'seller_id' => \App\Support\Shop::idForSeller($seller ?? $this->seller), 'status' => $status,
            'subtotal' => $subtotal, 'shipping_fee' => 50, 'total' => $subtotal + 50, 'currency' => 'PHP',
            'payment_method' => 'cod', 'recipient_name' => 'Maria Buyer', 'phone' => '09171234567',
            'shipping_address' => 'Test Street, Manila', 'placed_at' => now(),
        ]);
        foreach ($items as [$product, $quantity, $price]) {
            DB::table('order_items')->insert([
                'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
                'unit_price' => $price, 'quantity' => $quantity, 'line_total' => $price * $quantity,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $order;
    }

    private function returnCase(\App\Models\Order $order, array $attributes = []): Dispute
    {
        return Dispute::create($attributes + [
            'order_id' => $order->id, 'opened_by' => $this->buyer->id, 'status' => 'in_review', 'category' => 'damaged',
            'reason' => 'The item arrived with a torn seam.', 'evidence' => [], 'return_type' => 'return_refund',
        ]);
    }

    private function checkout(Product $product, int $quantity): void
    {
        $this->buyer->forceFill(['identity_verified_at' => now()])->save();
        $address = $this->buyer->addresses()->first() ?? $this->buyer->addresses()->create($this->savedAddressData());
        $cart = Cart::firstOrCreate(['user_id' => $this->buyer->id]);
        $cart->items()->delete();
        $item = $cart->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->fresh()->defaultVariant?->id, 'quantity' => $quantity]);

        app(CheckoutService::class)->place($this->buyer->fresh(), [$item->id], [
            'address_id' => $address->id, 'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cod',
        ], '');
    }
}
