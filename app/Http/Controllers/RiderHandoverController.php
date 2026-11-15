<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\ActivityLogger;
use App\Services\RiderAttendanceClock;
use App\Services\RiderDeliveries;
use App\Services\ShipmentWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderHandoverController extends Controller
{
    public function confirm(Request $request, Shipment $shipment)
    {
        return DB::transaction(function () use ($request, $shipment) {
            $user = $request->user();
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $user->rider?->logistics_provider_id === (int) $locked->logistics_provider_id, 403);
            $pickup = (int) $locked->pickup_courier_id === (int) $user->id && ! $locked->hub_received_at;
            $delivery = app(RiderDeliveries::class)->owns($locked, $user) && ShipmentWorkflow::stageOf($locked) === 'out_for_delivery';
            abort_unless($pickup || $delivery, 403);
            $scan = $pickup ? $locked->pickup_scanned_at : $locked->delivery_scanned_at;
            if (! $scan) throw ValidationException::withMessages(['handover' => 'Scan the seller or hub waybill before confirming handover.']);
            $column = $pickup ? 'rider_handover_confirmed_at' : 'delivery_handover_confirmed_at';
            if (! $locked->$column) {
                $locked->update([$column => now()]);
                app(ActivityLogger::class)->record('rider.handover_confirmed', 'logistics', 'Rider confirmed scanned parcel handover.', $user, $locked, ['leg' => $pickup ? 'pickup' : 'delivery']);
            }
            return response()->json(['message' => $pickup ? 'Handover confirmed. Logistics must receive and check the parcel to credit pickup earnings.' : 'Hub handover confirmed. You may proceed with delivery.']);
        });
    }
}
