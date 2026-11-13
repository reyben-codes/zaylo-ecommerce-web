<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\ChatPhotos;
use App\Services\RiderDeliveries;
use App\Services\ShipmentWorkflow;
use App\Support\ParcelView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MobileRiderDeliveryController extends Controller
{
    public function __construct(private readonly RiderDeliveries $deliveries) {}

    /**
     * Assigned hub-dispatched parcels (out-for-delivery manifest).
     */
    public function manifest(Request $request): JsonResponse
    {
        $rider = $request->user();
        $relations = array_values(array_filter([
            Schema::hasColumn('shipments', 'order_id') ? 'order.items' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder.items' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder.order' : null,
            Schema::hasColumn('shipments', 'logistics_provider_id') ? 'provider' : null,
            RiderDeliveries::tracksDecisions() ? 'riderLogs' : null,
        ]));
        $active = $this->deliveries->active($rider)->with($relations)->latest('updated_at')->get();

        return response()->json([
            'role' => 'delivery_rider',
            'count' => $active->count(),
            'manifest' => $active->map(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'tracking_number' => RiderDeliveries::labelCode($shipment),
                'stage' => RiderDeliveries::riderStage($shipment),
                'recipient_name' => ParcelView::for($shipment)['recipient'],
                'destination' => ParcelView::for($shipment)['destination'],
                'area' => ParcelView::for($shipment)['area'],
                'phone' => ParcelView::for($shipment)['phone'],
                'amounts' => RiderDeliveries::amounts($shipment),
            ])->values(),
        ]);
    }

    /**
     * Scan parcel label to transition to OUT_FOR_DELIVERY or DELIVERED.
     */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:100'] + ChatPhotos::rules(), ChatPhotos::messages());
        [$shipment, $step] = $this->deliveries->scan($request->user(), $data['code'], $request->file('photo'));

        return response()->json([
            'message' => $step === 'delivered'
                ? 'Delivered successfully with proof of delivery.'
                : 'Parcel marked as Out for Delivery.',
            'step' => $step,
            'shipment_id' => $shipment->id,
            'tracking_number' => RiderDeliveries::labelCode($shipment),
            'status' => ShipmentWorkflow::stageOf($shipment),
        ]);
    }

    /**
     * Buyer proof-of-delivery (POD) photo upload.
     */
    public function proof(Request $request, Shipment $shipment, ChatPhotos $photos): JsonResponse
    {
        abort_unless($this->deliveries->owns($shipment, $request->user()), 403, 'This delivery is assigned to another rider.');
        abort_unless(Shipment::tracksDeliveryProof(), 503, 'Delivery photos are being set up. Please try again later.');

        $request->validate(['photo' => ['required', ...ChatPhotos::rules()['photo']]], ChatPhotos::messages() + [
            'photo.required' => 'Take a photo of the parcel at the door.',
        ]);

        $deliveredAt = RiderDeliveries::deliveredAt($shipment);
        if (ShipmentWorkflow::stageOf($shipment) !== 'delivered' || ($deliveredAt && $deliveredAt->lt(now()->subDay()))) {
            throw ValidationException::withMessages(['photo' => 'Add the photo within a day of delivering the parcel.']);
        }

        $old = $shipment->delivery_proof_path;
        $path = $photos->store($request->file('photo'), 'delivery-proof/'.$shipment->id);
        $shipment->update(['delivery_proof_path' => $path]);
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return response()->json([
            'message' => 'Proof of delivery recorded successfully.',
            'proof_url' => $path,
            'shipment_id' => $shipment->id,
        ]);
    }

    /**
     * Record a failed delivery attempt.
     */
    public function fail(Request $request, Shipment $shipment): JsonResponse
    {
        abort_unless($this->deliveries->owns($shipment, $request->user()), 403, 'This delivery is assigned to another rider.');
        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', array_keys(RiderDeliveries::FAILED_REASONS))],
            'note' => ['nullable', 'string', 'max:500', 'required_if:reason,other'],
        ], [
            'reason.required' => 'Choose why the parcel could not be delivered.',
            'note.required_if' => 'Describe what happened.',
        ]);

        $shipment = $this->deliveries->advance(
            $shipment,
            $request->user(),
            'failed',
            reason: $data['reason'],
            note: $data['note'] ?? null
        );

        return response()->json([
            'message' => 'Delivery marked as failed. Logistics partner has been notified.',
            'status' => 'failed',
            'shipment_id' => $shipment->id,
        ]);
    }
}
