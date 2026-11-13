<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RiderSellerPickups
{
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
            if (! $shipment || ($shipment->pickup_courier_id && (int) $shipment->pickup_courier_id !== $user->id)) {
                throw ValidationException::withMessages(['code' => 'This pickup is unavailable or belongs to another rider or logistics partner.']);
            }
            $seller = $shipment->sellerOrder?->seller;
            $owner = $seller?->owner ?? $shipment->order?->seller;
            $address = $seller?->pickupAddress ?? $owner?->addresses()->orderByDesc('is_default')->first();
            $area = collect([$address?->city, $address?->province ?: $address?->region_name])->filter()->implode(', ');
            if (app(RiderProximity::class)->serving(collect([$rider]), $area)->isEmpty()) {
                throw ValidationException::withMessages(['code' => 'This seller pickup is outside the city in your saved address.']);
            }
            if (! array_key_exists('picked_up', ShipmentWorkflow::nextSteps($shipment))) {
                throw ValidationException::withMessages(['code' => 'This parcel is not confirmed for pickup, or has already been collected.']);
            }
            $shipment->update(['pickup_courier_id' => $user->id]);
            app(ShipmentWorkflow::class)->advance($shipment, 'picked_up', $user, 'Collected from seller by rider '.$user->name.' after scanning the waybill.');

            return $shipment->fresh();
        });
    }
}
