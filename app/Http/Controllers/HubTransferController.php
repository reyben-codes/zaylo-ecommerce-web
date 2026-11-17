<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesLogisticsParcels;
use App\Models\LogisticsProvider;
use App\Models\Shipment;
use App\Services\HubRouting;
use App\Services\ShipmentWorkflow;
use Illuminate\Http\Request;

class HubTransferController extends Controller
{
    use QueriesLogisticsParcels;

    public function index(Request $request, HubRouting $routing)
    {
        abort_unless(HubRouting::available() && ($hub = $this->logisticsProvider()), 404);
        $toSend = ShipmentWorkflow::whereStage($this->withParcelDetails($this->parcels()), ['in_transit'])
            ->whereNull('destination_received_at')->oldest()->paginate(20, ['*'], 'outgoing_page');
        $incoming = $this->withParcelDetails(Shipment::where('destination_hub_id', $hub->id))
            ->whereNotNull('hub_dispatched_at')->whereNull('destination_received_at')->with(['originHub', 'destinationHub'])
            ->oldest('hub_dispatched_at')->paginate(20, ['*'], 'incoming_page');
        $history = $this->withParcelDetails(Shipment::where(fn ($query) => $query->where('origin_hub_id', $hub->id)->orWhere('destination_hub_id', $hub->id)))
            ->whereNotNull('hub_dispatched_at')->with(['originHub', 'destinationHub', 'currentHub'])->latest('hub_dispatched_at')->limit(50)->get();

        return view('logistics.operations.hub-transfers', compact('hub', 'toSend', 'incoming', 'history', 'routing'));
    }

    public function dispatch(Request $request, Shipment $shipment, HubRouting $routing)
    {
        $data = $request->validate(['destination_hub_id' => 'required|integer|exists:logistics_providers,id', 'tracking' => 'required|string|max:100']);
        $routing->dispatch($shipment, LogisticsProvider::findOrFail($data['destination_hub_id']), $request->user(), $data['tracking']);

        return back()->with('status', 'Parcel dispatched to its destination hub. Final delivery stays blocked until receipt and barangay sorting.');
    }

    public function receive(Request $request, Shipment $shipment, HubRouting $routing)
    {
        $data = $request->validate(['tracking' => 'required|string|max:100', 'parcel_received' => 'accepted', 'tracking_verified' => 'accepted']);
        $routing->receive($shipment, $request->user(), $data['tracking']);

        return redirect()->route('logistics.sorting')->with('status', 'Transfer received. Sort the parcel into its saved delivery barangay before assigning a rider.');
    }
}
