<?php

namespace App\Support;

use App\Models\Shipment;
use App\Services\ShipmentWorkflow;
use Illuminate\Support\Facades\Schema;

/**
 * What the logistics pages show for a parcel, the same on the deployed and newer schemas.
 */
class ParcelView
{
    public static function for(Shipment $shipment): array
    {
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        $address = $order?->shipping_address;
        $destination = is_array($address)
            ? collect([data_get($address, 'line1'), data_get($address, 'barangay'), data_get($address, 'city'), data_get($address, 'province')])->filter()->implode(', ')
            : (string) $address;
        $recipient = $order?->recipient_name ?: (is_array($address) ? (data_get($address, 'recipient') ?: data_get($address, 'recipient_name')) : null);
        $seller = $shipment->sellerOrder?->seller?->name
            ?? ($order?->seller ? $order->seller->sellerStoreName() : null);
        $rider = Schema::hasColumn('shipments', 'rider_id') && $shipment->rider_id
            ? $shipment->rider?->user?->name
            : ($shipment->courier_id ? $shipment->courier?->name : null);

        return [
            'tracking' => $shipment->tracking_number ?? $shipment->tracking_code ?? 'Parcel #'.$shipment->id,
            'order' => $order?->order_number ?: '—',
            'seller' => $seller ?: '—',
            'recipient' => $recipient ?: '—',
            'phone' => $order?->phone,
            'destination' => $destination ?: '—',
            'rider' => $rider,
            'stage' => ShipmentWorkflow::stageOf($shipment),
            'label' => ShipmentWorkflow::labelOf($shipment),
            'area' => ShipmentWorkflow::hasHubSteps() ? $shipment->sort_area : null,
            'pickup_on' => ShipmentWorkflow::hasHubSteps() ? $shipment->pickup_scheduled_for : null,
            'updated' => $shipment->updated_at,
        ];
    }
}
