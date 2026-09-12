<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourierController extends Controller
{
    public function dashboard()
    {
        return view('courier.dashboard', [
            'user' => auth()->user(),
            'availableCount' => Shipment::whereNull('courier_id')->where('status', 'ready')->count(),
            'activeCount' => Shipment::where('courier_id', auth()->id())->whereNotIn('status', ['delivered', 'cancelled'])->count(),
            'completedCount' => Shipment::where('courier_id', auth()->id())->where('status', 'delivered')->count(),
        ]);
    }

    public function deliveries()
    {
        $available = Shipment::whereNull('courier_id')->where('status', 'ready')->with('order')->oldest()->get();
        $assigned = Shipment::where('courier_id', auth()->id())->with('order.items')->latest()->get();
        return view('courier.deliveries', compact('available', 'assigned'));
    }

    public function claim(Shipment $shipment)
    {
        DB::transaction(function () use ($shipment) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->courier_id || $locked->status !== 'ready') {
                throw ValidationException::withMessages(['delivery' => 'This delivery is no longer available.']);
            }
            $locked->update(['courier_id' => auth()->id(), 'status' => 'assigned']);
            $locked->order()->update(['status' => 'assigned']);
            $locked->order->statusHistory()->create([
                'changed_by' => auth()->id(), 'status' => 'assigned', 'note' => 'Courier accepted delivery.',
            ]);
        });
        return back()->with('status', 'Delivery accepted.');
    }

    public function updateDelivery(Request $request, Shipment $shipment)
    {
        abort_unless($shipment->courier_id === auth()->id(), 403);
        $data = $request->validate(['status' => 'required|in:picked_up,in_transit,delivered']);
        $allowed = ['assigned' => ['picked_up'], 'picked_up' => ['in_transit'], 'in_transit' => ['delivered']];

        DB::transaction(function () use ($shipment, $data, $allowed) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if (! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'That delivery status transition is not allowed.']);
            }
            $timestamps = match ($data['status']) {
                'picked_up' => ['picked_up_at' => now()],
                'delivered' => ['delivered_at' => now()],
                default => [],
            };
            $locked->update(['status' => $data['status']] + $timestamps);
            $orderStatus = $data['status'] === 'delivered' ? 'completed' : $data['status'];
            $locked->order()->update(['status' => $orderStatus] + ($orderStatus === 'completed' ? ['completed_at' => now()] : []));
            $locked->order->statusHistory()->create([
                'changed_by' => auth()->id(), 'status' => $orderStatus, 'note' => 'Updated by courier.',
            ]);
            if ($data['status'] === 'delivered') {
                $locked->order->payment?->update(['status' => 'paid', 'paid_at' => now()]);
                if ($locked->order->seller_id) {
                    DB::table('commissions')->updateOrInsert(
                        ['order_id' => $locked->order_id],
                        [
                            'seller_id' => $locked->order->seller_id, 'rate' => 10,
                            'amount' => round((float) $locked->order->subtotal * 0.10, 2),
                            'status' => 'earned', 'created_at' => now(), 'updated_at' => now(),
                        ]
                    );
                }
            }
        });
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
