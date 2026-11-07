<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\ShipmentWorkflow;
use App\Support\ParcelView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * First-mile Courier portal: pick up parcels from sellers and deliver them to the logistics partner's sorting hub.
 */
class CourierController extends Controller
{
    public function __construct(private readonly ShipmentWorkflow $workflow) {}

    public function dashboard()
    {
        $courierId = auth()->id();
        $provider = auth()->user()->rider?->provider;

        return view('courier.dashboard', [
            'user' => auth()->user(),
            'company' => $provider?->name,
            'availableCount' => Shipment::where('logistics_provider_id', $provider?->id)
                ->whereNull('pickup_courier_id')
                ->whereIn('status', ['ready', 'confirmed'])
                ->count(),
            'activeCount' => Shipment::where('pickup_courier_id', $courierId)
                ->whereIn('status', ['ready', 'confirmed', 'picked_up'])
                ->count(),
            'completedCount' => Shipment::where('pickup_courier_id', $courierId)
                ->whereNotIn('status', ['ready', 'confirmed', 'picked_up'])
                ->count(),
        ]);
    }

    public function deliveries()
    {
        $courierId = auth()->id();
        $provider = auth()->user()->rider?->provider;

        // Open pickup requests from sellers waiting for a courier
        $available = Shipment::where('logistics_provider_id', $provider?->id)
            ->whereNull('pickup_courier_id')
            ->whereIn('status', ['ready', 'confirmed'])
            ->with(['order.items', 'order.seller.addresses', 'sellerOrder.seller.pickupAddress'])
            ->oldest()
            ->get();

        // Pickups assigned to this courier
        $assigned = Shipment::where('pickup_courier_id', $courierId)
            ->whereIn('status', ['ready', 'confirmed', 'picked_up'])
            ->with(['order.items', 'order.seller.addresses', 'sellerOrder.seller.pickupAddress'])
            ->latest('updated_at')
            ->get();

        return view('courier.deliveries', compact('available', 'assigned'));
    }

    /** Courier accepts an assigned seller pickup task. */
    public function claim(Shipment $shipment)
    {
        DB::transaction(function () use ($shipment) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->pickup_courier_id || ! in_array($locked->status, ['ready', 'confirmed'], true)) {
                throw ValidationException::withMessages(['delivery' => 'This pickup is no longer available.']);
            }
            $locked->update(['pickup_courier_id' => auth()->id()]);
            $locked->order?->statusHistory()->create([
                'changed_by' => auth()->id(),
                'status' => $locked->status,
                'note' => 'Courier '.auth()->user()->name.' accepted the pickup task.',
            ]);
        });

        return back()->with('status', 'Pickup task accepted.');
    }

    /**
     * Courier updates parcel step:
     * - picked_up: Collected from seller (requires scanning/entering the waybill code).
     * - in_transit: Handed over to the logistics partner hub.
     */
    public function updateDelivery(Request $request, Shipment $shipment)
    {
        abort_unless((int) $shipment->pickup_courier_id === (int) auth()->id(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['picked_up', 'in_transit'])],
            'tracking' => ['exclude_unless:status,picked_up', 'required', 'string', 'max:100'],
        ], [
            'tracking.required' => "Scan or enter the tracking number on the parcel's waybill to confirm pickup.",
        ]);

        $tracking = $shipment->tracking_number ?? $shipment->tracking_code;
        if ($data['status'] === 'picked_up' && strcasecmp(trim($data['tracking']), $tracking) !== 0) {
            throw ValidationException::withMessages([
                'tracking' => "The entered code does not match parcel {$tracking}. Check that you have the right package.",
            ]);
        }

        $note = $data['status'] === 'picked_up'
            ? 'Collected from seller by courier '.auth()->user()->name.'.'
            : 'Delivered to logistics hub by courier '.auth()->user()->name.'.';

        $this->workflow->advance($shipment, $data['status'], auth()->user(), $note);

        return back()->with('status', $data['status'] === 'picked_up'
            ? "Parcel {$tracking} marked as picked up from seller. Transport it to the hub."
            : "Parcel {$tracking} handed over to the logistics hub.");
    }

    public function earnings()
    {
        $shipments = Shipment::where('pickup_courier_id', auth()->id())
            ->whereNotIn('status', ['ready', 'confirmed'])
            ->with('order')
            ->latest()
            ->paginate(20);

        return view('courier.earnings', compact('shipments'));
    }

    public function history()
    {
        $shipments = Shipment::where('pickup_courier_id', auth()->id())
            ->whereNotIn('status', ['ready', 'confirmed', 'picked_up'])
            ->with(['order.items', 'order.seller'])
            ->latest('updated_at')
            ->paginate(20);

        return view('courier.history', compact('shipments'));
    }

    public function account()
    {
        return view('courier.account', [
            'user' => auth()->user(),
            'rider' => auth()->user()->rider,
            'company' => auth()->user()->rider?->provider?->name,
        ]);
    }
}
