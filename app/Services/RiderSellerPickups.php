<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RiderSellerPickups
{
    public static function pickupArea(Shipment $shipment): string
    {
        $seller = $shipment->sellerOrder?->seller;
        $owner = $seller?->owner ?? $shipment->order?->seller;
        $address = $seller?->pickupAddress ?? $owner?->addresses()->orderByDesc('is_default')->first();

        return collect([$address?->city, $address?->province ?: $address?->region_name])->filter()->implode(', ');
    }

    public function forRider(User $user): \Illuminate\Support\Collection
    {
        if (! $user->rider?->logistics_provider_id) return collect();

        $query = Shipment::where('pickup_courier_id', $user->id)
            ->where('logistics_provider_id', $user->rider->logistics_provider_id);
        $active = ShipmentWorkflow::whereStage(clone $query, ['ready', 'confirmed', 'picked_up'])->latest('updated_at')->get();
        $recent = ShipmentWorkflow::whereStage(clone $query, ['in_transit', 'sorted', 'assigned', 'out_for_delivery', 'delivered', 'failed', 'returned'])
            ->latest('updated_at')->limit(20)->get();

        return $active->concat($recent)->map(function (Shipment $shipment) {
                $parcel = \App\Support\ParcelView::for($shipment);
                $items = $shipment->sellerOrder?->items ?? $shipment->order?->items ?? collect();
                return [
                    'id' => $shipment->id, 'tracking_number' => RiderDeliveries::labelCode($shipment),
                    'order_number' => $parcel['order'], 'seller' => $parcel['seller'], 'pickup_address' => $parcel['pickup_address'],
                    'status' => $shipment->hub_received_at ? 'handed_over' : ShipmentWorkflow::stageOf($shipment),
                    'scanned_at' => $shipment->pickup_scanned_at?->toIso8601String(),
                    'received_at' => $shipment->hub_received_at?->toIso8601String(),
                    'items' => $items->map(fn ($item) => ['name' => $item->product_name, 'quantity' => (int) $item->quantity])->values(),
                ];
            });
    }

    public function scan(User $user, string $code): Shipment
    {
        return DB::transaction(function () use ($user, $code) {
            $rider = $user->rider()->with('user.addresses')->first();
            if (! $rider || ! $user->hasRole('rider') || $user->status !== 'active'
                || $rider->status !== 'approved' || ! $rider->is_active || ! $rider->logistics_provider_id
                || ! RiderAttendanceClock::whereOnShift(\App\Models\Rider::whereKey($rider->id))->exists()) {
                throw ValidationException::withMessages(['code' => 'Time in at your logistics hub before collecting seller parcels.']);
            }
            $column = Schema::hasColumn('shipments', 'tracking_number') ? 'tracking_number' : 'tracking_code';
            $shipment = Shipment::where('logistics_provider_id', $rider->logistics_provider_id)
                ->whereRaw('UPPER('.$column.') = ?', [strtoupper(trim($code))])->lockForUpdate()->first();
            if (! $shipment || (int) $shipment->pickup_courier_id !== $user->id) {
                throw ValidationException::withMessages(['code' => 'This pickup is unavailable or belongs to another rider or logistics partner.']);
            }
            $area = self::pickupArea($shipment);
            if (app(RiderProximity::class)->serving(collect([$rider]), $area)->isEmpty()) {
                throw ValidationException::withMessages(['code' => 'This seller pickup is outside the city in your saved address.']);
            }
            if (! array_key_exists('picked_up', ShipmentWorkflow::nextSteps($shipment))) {
                throw ValidationException::withMessages(['code' => 'This parcel is not confirmed for pickup, or has already been collected.']);
            }
            $shipment->update(['pickup_courier_id' => $user->id] + (Schema::hasColumn('shipments', 'pickup_scanned_at') ? ['pickup_scanned_at' => now()] : []));
            app(ShipmentWorkflow::class)->advance($shipment, 'picked_up', $user, 'Collected from seller by rider '.$user->name.' after scanning the waybill.');

            return $shipment->fresh();
        });
    }
}
