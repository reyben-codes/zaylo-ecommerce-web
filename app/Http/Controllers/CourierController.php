<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function dashboard()
    {
        $deliveries = app(DeliveryService::class);

        return view('courier.dashboard', [
            'user' => auth()->user(),
            'availableCount' => $deliveries->available()->count(),
            'activeCount' => $deliveries->active(auth()->user())->count(),
            'completedCount' => $deliveries->completed(auth()->user())->count(),
        ]);
    }

    public function deliveries()
    {
        $available = Shipment::whereNull('courier_id')->where('status', 'ready')->with('order')->oldest()->get();
        $assigned = Shipment::where('courier_id', auth()->id())->with('order.items')->latest()->get();
        return view('courier.deliveries', compact('available', 'assigned'));
    }

    public function claim(Shipment $shipment, DeliveryService $deliveries)
    {
        $deliveries->claim($shipment, auth()->user());

        return back()->with('status', 'Delivery accepted.');
    }

    public function updateDelivery(Request $request, Shipment $shipment, DeliveryService $deliveries)
    {
        abort_unless($shipment->courier_id === auth()->id(), 403);
        $data = $request->validate(['status' => 'required|in:picked_up,in_transit,delivered']);
        $deliveries->advance($shipment, auth()->user(), $data['status']);

        return back()->with('status', 'Delivery status updated.');
    }

    public function earnings()
    {
        $shipments = Shipment::where('courier_id', auth()->id())->where('status', 'delivered')->with('order')->latest()->get();
        return view('courier.earnings', compact('shipments'));
    }

    public function history() { return $this->deliveries(); }
    public function account() { return view('courier.account'); }
    public function chat() { return view('courier.chat'); }
}
