<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Support\OrderParties;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The rider's photo of a delivered parcel, for the buyer, seller and rider of the order and the admin.
 */
class DeliveryProofController extends Controller
{
    public function show(Request $request, Shipment $shipment)
    {
        $user = $request->user();
        $parties = OrderParties::for($shipment->order ?: $shipment->sellerOrder?->order);
        abort_unless($user->hasRole('admin') || in_array((int) $user->id, array_filter($parties), true), 403);

        $path = $shipment->delivery_proof_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
