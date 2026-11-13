<?php

namespace App\Http\Controllers;

use App\Services\RiderSellerPickups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileRiderPickupController extends Controller
{
    public function __construct(private readonly RiderSellerPickups $pickups) {}

    /**
     * Assigned pickup queue for the pickup rider.
     */
    public function queue(Request $request): JsonResponse
    {
        $queue = $this->pickups->assignedQueue($request->user());

        return response()->json([
            'role' => 'pickup_rider',
            'count' => count($queue),
            'queue' => $queue,
        ]);
    }

    /**
     * Barcode/QR scanner at the seller location. Transitions parcel to PICKED_UP_IN_TRANSIT.
     */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
        ]);

        $shipment = $this->pickups->scan($request->user(), $data['code']);

        return response()->json([
            'message' => 'Parcel scanned at seller. It has been added to your active transit manifest.',
            'status' => 'picked_up_in_transit',
            'pickup' => [
                'id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'pickup_scanned_at' => $shipment->pickup_scanned_at?->toIso8601String(),
                'status' => 'picked_up',
            ],
        ]);
    }

    /**
     * Active transit manifest for parcels collected from sellers and en route to the hub.
     */
    public function manifest(Request $request): JsonResponse
    {
        $manifest = $this->pickups->activeManifest($request->user());

        return response()->json([
            'role' => 'pickup_rider',
            'count' => count($manifest),
            'manifest' => $manifest,
        ]);
    }

    /**
     * Parcels being dropped off or already received at the logistics hub.
     */
    public function hubDropoff(Request $request): JsonResponse
    {
        $dropoffs = $this->pickups->hubDropoff($request->user());

        return response()->json([
            'role' => 'pickup_rider',
            'count' => count($dropoffs),
            'dropoffs' => $dropoffs,
        ]);
    }
}

