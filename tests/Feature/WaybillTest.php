<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Code128;
use App\Support\Waybill;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Waybills on the deployed order schema (orders carry the seller), which is what sellers use.
 */
class WaybillTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareLegacyOrderSchema();
    }

    public function test_seller_prints_a_waybill_once_the_order_is_ready_to_ship(): void
    {
        [$seller, $order] = $this->sellerOrder('processing');
        $this->actingAs($seller);

        // Before it is ready to ship there is no tracking number, so no waybill.
        $this->get(route('seller.orders'))->assertOk()->assertDontSee(route('seller.orders.waybill.print', $order));
        $this->get(route('seller.orders.waybill', $order))->assertRedirect(route('seller.orders'))
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Ready to ship first'));

        $this->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup'])->assertSessionHasNoErrors();
        $tracking = $order->fresh()->shipment->tracking_number;
        $this->get(route('seller.orders'))->assertOk()->assertSee(route('seller.orders.waybill.print', $order));

        $response = $this->get(route('seller.orders.waybill', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString("waybill-{$tracking}.pdf", $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_waybill_shows_sender_recipient_contents_and_cash_to_collect(): void
    {
        [$seller, $order] = $this->sellerOrder('ready_for_pickup');
        $shipment = $order->shipment()->create(['tracking_number' => 'TRK-ABC123XYZ', 'status' => 'ready']);

        $waybill = Waybill::for($shipment);
        $this->assertSame('Linen House', $waybill['sender']['name']);
        $this->assertSame('09170000001', $waybill['sender']['phone']);
        $this->assertSame(['name' => 'Ana Reyes', 'phone' => '09171234567', 'address' => '78 Osmeña Blvd, Cebu City, Cebu'], $waybill['recipient']);
        $this->assertSame(3, $waybill['pieces']);
        $this->assertSame(1300.0, $waybill['cod']);
        $this->assertSame('Cash on delivery', $waybill['payment']);

        $html = view('waybill.pdf', ['waybill' => $waybill])->render();
        foreach (['TRK-ABC123XYZ', $order->order_number, 'Ana Reyes', '78 Osmeña Blvd, Cebu City, Cebu', 'Linen House', 'Linen Sheet', 'Cotton Pillowcase', 'COLLECT ON DELIVERY', '&#8369;1,300.00', 'data:image/svg+xml;base64,', 'data:image/png;base64,', 'QR code for TRK-ABC123XYZ'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        // The QR code next to the barcode holds the same tracking number the rider app scans.
        $png = base64_decode(substr($waybill['qr'], strlen('data:image/png;base64,')));
        $this->assertSame('TRK-ABC123XYZ', (new \chillerlan\QRCode\QRCode)->readFromBlob($png)->data);

        // Paid online: the rider collects nothing.
        $order->update(['payment_method' => 'wallet']);
        $paid = Waybill::for($shipment->fresh());
        $this->assertSame(0.0, $paid['cod']);
        $this->assertSame('Paid online', $paid['payment']);
        $this->assertStringContainsString('Do not collect', view('waybill.pdf', ['waybill' => $paid])->render());
    }

    public function test_only_the_orders_seller_can_print_it_and_cancelled_orders_have_none(): void
    {
        [$seller, $order] = $this->sellerOrder('ready_for_pickup');
        $order->shipment()->create(['tracking_number' => 'TRK-OWNED', 'status' => 'ready']);

        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($other)->get(route('seller.orders.waybill', $order))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))->get(route('seller.orders.waybill', $order))->assertForbidden();

        // A cancelled order's parcel is cancelled too (e.g. cancelled before pickup by an admin).
        $order->update(['status' => 'cancelled']);
        $order->shipment->update(['status' => 'cancelled']);
        $this->actingAs($seller)->get(route('seller.orders.waybill', $order))->assertRedirect(route('seller.orders'))
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'cancelled'));
        $this->get(route('seller.orders', ['status' => 'cancelled']))->assertDontSee(route('seller.orders.waybill.print', $order));
    }

    public function test_tracking_barcodes_are_valid_code_128(): void
    {
        foreach (Code128::PATTERNS as $value => $pattern) {
            $this->assertSame($value === 106 ? 13 : 11, array_sum(str_split($pattern)), "Pattern {$value} has the wrong width.");
        }
        $this->assertCount(107, array_unique(Code128::PATTERNS));

        $widths = Code128::widths('TRK-ABC123');
        // Start B + 10 characters + checksum, 11 modules each, then the 13-module stop.
        $this->assertSame(11 * 12 + 13, array_sum($widths));
        $this->assertSame([2, 1, 1, 2, 1, 4], array_slice($widths, 0, 6));
        $this->assertSame([2, 3, 3, 1, 1, 1, 2], array_slice($widths, -7));
        $this->assertStringContainsString('<rect', Code128::svg('TRK-ABC123'));

        $this->expectException(\InvalidArgumentException::class);
        Code128::widths("TRK\u{00D1}");
    }

    public function test_sellers_without_a_printer_get_a_queue_mark_and_the_partner_prints(): void
    {
        (require database_path('migrations/2026_11_07_000009_add_print_queue_to_shipments.php'))->up();
        [$seller, $order] = $this->sellerOrder('processing');
        [, $second] = $this->sellerOrder('processing');
        $second->update(['seller_id' => $seller->id]);
        $this->actingAs($seller);

        $this->get(route('seller.orders'))->assertOk()->assertSee("I can't print the waybill", false);
        $this->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup', 'partner_prints' => '1'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($status) => str_contains($status, 'Write 01 RED big on the parcel'));
        $this->patch(route('seller.orders.status', $second), ['status' => 'ready_for_pickup', 'partner_prints' => '1']);

        // Numbers run up through the day, and the colour changes with each so neighbours never look alike.
        $this->assertSame([1, 'red', true], [$order->fresh()->shipment->queue_number, $order->fresh()->shipment->queue_color, (bool) $order->fresh()->shipment->partner_prints]);
        $this->assertSame([2, 'blue'], [$second->fresh()->shipment->queue_number, $second->fresh()->shipment->queue_color]);
        $this->get(route('seller.orders'))->assertSee('01 · RED')->assertSee('Write this big on the parcel.');

        // The printed waybill carries the same mark.
        $this->get(route('seller.orders.waybill.print', $order))->assertOk()->assertSee('01 · RED')->assertSee('Match to the parcel marked 01 RED');
    }

    public function test_each_courier_gets_its_own_waybill_layout(): void
    {
        [$seller, $order] = $this->sellerOrder('processing');
        $this->actingAs($seller)->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup']);
        $print = route('seller.orders.waybill.print', $order);

        // ZAYLO Express (the default) keeps the ZAYLO label.
        $this->get($print)->assertOk()->assertSee('class="wb-courier-zaylo-express wb-web"', false)->assertSee('WAYBILL')->assertDontSee('J&amp;T EXPRESS', false);

        if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'courier_choice')) {
            $order->update(['courier_choice' => 'jnt_express']);
            $this->get($print)->assertOk()->assertSee('wb-courier-jnt-express', false)->assertSee('J&amp;T Express', false)
                ->assertSee('RECEIVER')->assertSee('SENDER')->assertSee('CEB-CEB');

            $order->update(['courier_choice' => 'flash_express']);
            $this->get($print)->assertOk()->assertSee('wb-courier-flash-express', false)->assertSee('Flash Express')
                ->assertSee('DST')->assertSee('CEB-CEB')->assertSee('Sorting line');
            $pdf = $this->get(route('seller.orders.waybill', $order))->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }
        $this->assertSame('LAG-CAL', Waybill::sortCode(['address' => '12 Rizal St, Calamba, Laguna']));
    }

    private function sellerOrder(string $status): array
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'phone' => '09170000001']);
        SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'Linen House']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'reference' => 'REF-'.uniqid(), 'total_minor' => 130000, 'payment_status' => 'pending',
            'order_number' => 'ZAY-'.strtoupper(uniqid()), 'user_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => $status,
            'subtotal' => 1200, 'shipping_fee' => 100, 'total' => 1300, 'currency' => 'PHP', 'payment_method' => 'cod',
            'recipient_name' => 'Ana Reyes', 'phone' => '09171234567', 'shipping_address' => '78 Osmeña Blvd, Cebu City, Cebu', 'placed_at' => now(),
        ]);
        foreach ([['Linen Sheet', 1, 800], ['Cotton Pillowcase', 2, 200]] as [$name, $quantity, $price]) {
            $order->items()->create(['seller_id' => $seller->id, 'product_name' => $name, 'sku' => 'SKU-'.$quantity, 'unit_price' => $price, 'quantity' => $quantity, 'line_total' => $price * $quantity]);
        }

        return [$seller, $order];
    }

    private function prepareLegacyOrderSchema(): void
    {
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('order_items');

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

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('order_status_histories');
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
            if (! Schema::hasColumn('orders', 'status')) $table->string('status')->default('placed');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            if (! Schema::hasColumn('orders', 'recipient_name')) $table->string('recipient_name')->nullable();
            if (! Schema::hasColumn('orders', 'phone')) $table->string('phone', 30)->nullable();
            if (! Schema::hasColumn('orders', 'notes')) $table->text('notes')->nullable();
            if (! Schema::hasColumn('orders', 'placed_at')) $table->timestamp('placed_at')->nullable();
            if (! Schema::hasColumn('orders', 'completed_at')) $table->timestamp('completed_at')->nullable();
            $table->decimal('shipping_discount', 12, 2)->default(0);
        });
    }
}
