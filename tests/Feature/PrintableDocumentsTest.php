<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Waybills and reports can be printed by the roles that need them: a print page that opens the print dialog,
 * a PDF, and Print buttons on report pages.
 */
class PrintableDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_waybill_print_page_opens_the_print_dialog_and_links_the_pdf(): void
    {
        [$owner, $provider] = $this->partner('Express Delivery');
        $shipment = $this->parcel('ZX-PRINT', $provider);

        $this->actingAs($owner)->get(route('logistics.scan', ['tracking' => 'ZX-PRINT']))
            ->assertSee(route('logistics.parcels.waybill.print', $shipment));
        $this->get(route('logistics.parcels.waybill.print', $shipment))->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('data-auto-print', false)
            ->assertSee('js/print.js', false)
            ->assertSee('Print label')
            ->assertSee(route('logistics.parcels.waybill', $shipment))
            ->assertSee('ZX-PRINT')
            ->assertSee('data:image/svg+xml;base64,', false);

        [$otherOwner] = $this->partner('Other Couriers');
        $this->actingAs($otherOwner)->get(route('logistics.parcels.waybill.print', $shipment))->assertForbidden();
    }

    public function test_admin_can_print_any_parcels_waybill_but_others_cannot_use_admin_links(): void
    {
        [, $provider] = $this->partner('Express Delivery');
        $shipment = $this->parcel('ZX-ADMIN', $provider);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.parcels.waybill.print', $shipment))->assertOk()->assertSee('ZX-ADMIN');
        $pdf = $this->get(route('admin.parcels.waybill', $shipment))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs(User::factory()->create(['role' => 'seller', 'status' => 'active']))
            ->get(route('admin.parcels.waybill.print', $shipment))->assertForbidden();
    }

    public function test_riders_print_waybills_only_for_their_own_parcels(): void
    {
        [, $provider] = $this->partner('Express Delivery');
        $shipment = $this->parcel('ZX-RIDER', $provider);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $rider->forceFill([])->save();
        $riderProfile = \App\Models\Rider::create(['user_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);

        $this->actingAs($rider)->get(route('courier.deliveries.waybill.print', $shipment))->assertForbidden();

        $shipment->update(['rider_id' => $riderProfile->id, 'status' => ShipmentWorkflow::stored('assigned')]);
        $this->get(route('courier.deliveries.waybill.print', $shipment))->assertOk()->assertSee('ZX-RIDER')
            ->assertSee(route('courier.deliveries.waybill', $shipment));
    }

    public function test_report_pages_have_print_buttons_and_the_seller_report_has_a_pdf(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('seller.reports'))->assertOk()
            ->assertSee('data-print', false)->assertSee('js/print.js', false)->assertSee('css/print.css', false)
            ->assertSee(route('seller.reports.pdf', ['from' => now('Asia/Manila')->subDays(29)->format('Y-m-d'), 'to' => now('Asia/Manila')->format('Y-m-d')]))
            ->assertSee('print-header', false);
        $pdf = $this->get(route('seller.reports.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringStartsWith('inline', $pdf->headers->get('Content-Disposition'));
        $this->get(route('seller.reports.pdf', ['from' => '2026-10-10', 'to' => '2026-10-01']))->assertSessionHasErrors('from');

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        // Admin reports are printed from the "Download report" PDF instead of a print button.
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk()->assertDontSee('data-print', false)->assertSee(route('admin.reports.download'));

        [$owner] = $this->partner('Express Delivery');
        $this->actingAs($owner)->get(route('logistics.reports'))->assertOk()->assertSee('data-print', false);

        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $this->actingAs($rider)->get(route('courier.earnings'))->assertOk()
            ->assertSee('Deliveries completed')->assertSee('Cash collected')->assertSee('data-print', false);
    }

    private function partner(string $name): array
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved']);

        return [$owner, $provider];
    }

    private function parcel(string $tracking, LogisticsProvider $provider): Shipment
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active'])->sellers()->create(['name' => 'Northline '.$tracking, 'slug' => 'northline-'.$tracking, 'status' => 'approved']);
        $order = Order::create([
            'buyer_id' => User::factory()->create(['role' => 'buyer'])->id, 'reference' => 'ZAY-'.$tracking, 'total_minor' => 10000,
            'payment_method' => 'cod', 'shipping_address' => ['recipient' => 'Juan Dela Cruz', 'city' => 'Pasig City', 'province' => 'Metro Manila'],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'ready_to_ship',
        ]);

        return Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'tracking_code' => $tracking, 'status' => ShipmentWorkflow::stored('ready')]);
    }
}
