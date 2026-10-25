<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesLogisticsParcels;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Support\Waybill;
use Illuminate\Support\Facades\Schema;

/**
 * Printable waybills. Each role opens the label as a print page (which brings up the print dialog) or as a PDF:
 * sellers for their orders once ready to ship, logistics partners for their parcels, riders for parcels assigned
 * to them, and admins for any parcel (e.g. while handling a dispute).
 */
class WaybillController extends Controller
{
    use QueriesLogisticsParcels;

    public function seller(Order $order)
    {
        return $this->forSeller($order, print: false);
    }

    public function sellerPrint(Order $order)
    {
        return $this->forSeller($order, print: true);
    }

    public function logistics(Shipment $shipment)
    {
        $this->ownsParcel($shipment);

        return $this->output($shipment, false, 'logistics.parcels.waybill');
    }

    public function logisticsPrint(Shipment $shipment)
    {
        $this->ownsParcel($shipment);

        return $this->output($shipment, true, 'logistics.parcels.waybill');
    }

    public function rider(Shipment $shipment)
    {
        abort_unless($this->ridesParcel($shipment), 403);

        return $this->output($shipment, false, 'courier.deliveries.waybill');
    }

    public function riderPrint(Shipment $shipment)
    {
        abort_unless($this->ridesParcel($shipment), 403);

        return $this->output($shipment, true, 'courier.deliveries.waybill');
    }

    public function admin(Shipment $shipment)
    {
        return $this->output($shipment, false, 'admin.parcels.waybill');
    }

    public function adminPrint(Shipment $shipment)
    {
        return $this->output($shipment, true, 'admin.parcels.waybill');
    }

    private function forSeller(Order $order, bool $print)
    {
        $shopId = Product::usesLegacySchema() ? (int) auth()->id() : (int) auth()->user()->sellerShop()->id;
        abort_unless((int) $order->seller_id === $shopId, 403);

        if (! Waybill::available($order->shipment)) {
            return redirect()->route('seller.orders')->with('status', $order->status === 'cancelled'
                ? "Order {$order->order_number} was cancelled, so it has no waybill."
                : "Mark order {$order->order_number} as Ready to ship first; its waybill and tracking number are created then.");
        }

        return $print
            ? Waybill::printView($order->shipment, route('seller.orders.waybill', $order))
            : Waybill::response($order->shipment);
    }

    /** The print page links to the PDF route of the same role. */
    private function output(Shipment $shipment, bool $print, string $pdfRoute)
    {
        abort_unless(Waybill::available($shipment), 404);

        return $print ? Waybill::printView($shipment, route($pdfRoute, $shipment)) : Waybill::response($shipment);
    }

    /** Riders see waybills only for parcels assigned to them. */
    private function ridesParcel(Shipment $shipment): bool
    {
        if (Schema::hasColumn('shipments', 'rider_id') && $shipment->rider_id) {
            return (int) $shipment->rider?->user_id === (int) auth()->id();
        }

        return $shipment->courier_id !== null && (int) $shipment->courier_id === (int) auth()->id();
    }
}
