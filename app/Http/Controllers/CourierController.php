<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\ChatPhotos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourierController extends Controller
{
    public function dashboard()
    {
        return view('courier.dashboard', [
            'user' => auth()->user(),
            // The logistics partner this rider delivers for.
            'company' => Schema::hasTable('riders') ? auth()->user()->rider?->provider?->name : null,
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

    /**
     * Moves the courier's parcel one step. Delivering needs a photo as proof, and leaves the order for the buyer to
     * confirm they received it (App\Services\OrderReceipts), which completes it.
     */
    public function updateDelivery(Request $request, Shipment $shipment, ChatPhotos $photos)
    {
        abort_unless($shipment->courier_id === auth()->id(), 403);
        $proof = Shipment::tracksDeliveryProof();
        $data = $request->validate([
            'status' => 'required|in:picked_up,in_transit,delivered',
            'photo' => [Rule::requiredIf($proof && $request->input('status') === 'delivered'), ...ChatPhotos::rules()['photo']],
        ], ChatPhotos::messages() + ['photo.required' => 'Take a photo of the parcel with the recipient or at the door.']);
        $allowed = ['assigned' => ['picked_up'], 'picked_up' => ['in_transit'], 'in_transit' => ['delivered']];
        $path = $data['status'] === 'delivered' && $proof ? $photos->store($request->file('photo'), 'delivery-proof/'.$shipment->id) : null;

        try {
            DB::transaction(function () use ($shipment, $data, $allowed, $path) {
                $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
                if (! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                    throw ValidationException::withMessages(['status' => 'That delivery status transition is not allowed.']);
                }
                $timestamps = match ($data['status']) {
                    'picked_up' => ['picked_up_at' => now()],
                    'delivered' => ['delivered_at' => now()],
                    default => [],
                };
                $locked->update(['status' => $data['status']] + $timestamps + ($path ? ['delivery_proof_path' => $path] : []));
                $delivered = $data['status'] === 'delivered' && Schema::hasColumn('orders', 'delivered_at');
                $locked->order()->update(['status' => $data['status']] + ($delivered ? ['delivered_at' => now()] : []));
                $locked->order->statusHistory()->create([
                    'changed_by' => auth()->id(), 'status' => $data['status'], 'note' => 'Updated by courier.',
                ]);
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return back()->with('status', $data['status'] === 'delivered'
            ? 'Marked as delivered. The buyer was asked to confirm they received it.'
            : 'Delivery status updated.');
    }

    public function earnings()
    {
        $shipments = Shipment::where('courier_id', auth()->id())->where('status', 'delivered')->with('order')->latest()->get();
        return view('courier.earnings', compact('shipments'));
    }

    public function history() { return $this->deliveries(); }
    public function account() { return view('courier.account'); }
}
