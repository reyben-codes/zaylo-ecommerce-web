<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Physical hub custody is separate from the seller's contracted logistics provider and payment snapshots. */
class HubRouting
{
    public static function available(): bool
    {
        return Schema::hasColumn('shipments', 'current_hub_id');
    }

    public static function location(Shipment $shipment): ?array
    {
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        $location = $order?->delivery_location;
        if (! is_array($location)) {
            $address = $order?->shipping_address;
            $location = is_array($address) && isset($address['city'], $address['barangay'], $address['city_municipality_code'], $address['barangay_code']) ? $address : null;
            // Recover older orders only when a structured saved address still matches the full order text exactly.
            // Changed or ambiguous addresses never silently reroute a parcel.
            $text = is_array($address) ? ($address['address'] ?? null) : $address;
            if (! $location && filled($text) && $order?->buyer) {
                $matches = $order->buyer->addresses->filter(fn ($saved) => $saved->isStructured() && $saved->formatted() === $text);
                $locations = $matches->map(fn ($saved) => $saved->only(['region_code', 'region_name', 'province_code', 'province', 'city_municipality_code', 'city', 'barangay_code', 'barangay', 'postal_code']))
                    ->unique(fn ($saved) => json_encode($saved))->values();
                if ($locations->count() === 1) $location = $locations->first();
            }
        }
        return is_array($location) && filled($location['city'] ?? null) && filled($location['barangay'] ?? null) ? $location : null;
    }

    public static function sameNetwork(LogisticsProvider $from, LogisticsProvider $to): bool
    {
        if (filled($from->network_brand) && filled($to->network_brand)) {
            return $from->network_brand === $to->network_brand;
        }
        // Older branches can share an explicitly recorded parent without a brand code.
        return (int) ($from->parent_id ?? $from->id) === (int) ($to->parent_id ?? $to->id);
    }

    public static function serves(LogisticsProvider $hub, array $location): bool
    {
        $code = (string) ($location['city_municipality_code'] ?? $location['city_code'] ?? '');
        if ($code !== '' && filled($hub->city_code)) return $code === (string) $hub->city_code;
        $normalize = fn ($text) => Str::lower(trim(preg_replace('/\s+/', ' ', Str::ascii((string) $text))));
        return filled($hub->city_name) && $normalize($hub->city_name) === $normalize($location['city'] ?? '')
            && filled($hub->province_name ?: $hub->region_name)
            && $normalize($hub->province_name ?: $hub->region_name) === $normalize($location['province'] ?? $location['region_name'] ?? '');
    }

    public static function area(Shipment $shipment): ?string
    {
        $location = self::location($shipment);
        return $location ? collect([$location['barangay'], $location['city'], $location['province'] ?? null])->filter()->implode(', ') : null;
    }

    public static function inTransfer(Shipment $shipment): bool
    {
        return $shipment->hub_dispatched_at !== null && $shipment->destination_received_at === null;
    }

    public static function needsTransfer(Shipment $shipment): bool
    {
        $location = self::location($shipment);
        $hub = $shipment->currentHub ?? $shipment->provider;
        return self::available() && $location !== null && $hub !== null && ! self::serves($hub, $location);
    }

    public static function requireLocalSorting(Shipment $shipment): void
    {
        if (! self::available()) return;
        if (self::inTransfer($shipment) || self::needsTransfer($shipment)) {
            throw ValidationException::withMessages(['shipment' => 'Transfer this parcel to a hub in the delivery city and confirm destination receipt before barangay sorting or rider assignment.']);
        }
    }

    public function destinations(Shipment $shipment): Collection
    {
        $origin = $shipment->originHub ?? $shipment->provider;
        $location = self::location($shipment);
        if (! $origin || ! $location) return collect();
        return LogisticsProvider::where('status', 'approved')->with('owner')->orderBy('name')->get()
            ->filter(fn ($hub) => $hub->owner?->isActive() && $hub->id !== $shipment->currentHubId()
                && self::sameNetwork($origin, $hub) && self::serves($hub, $location))
            ->when($shipment->rider_fee_quote && $shipment->destination_hub_id,
                fn ($hubs) => $hubs->where('id', $shipment->destination_hub_id))->values();
    }

    /** Save an unambiguous route before rider distances are quoted. Never invent a missing destination hub. */
    public function prepare(Shipment $shipment): void
    {
        if (! self::available() || ! self::location($shipment) || ! $shipment->provider || $shipment->hub_dispatched_at) return;
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        if ($order && ! $order->delivery_location && Schema::hasColumn('orders', 'delivery_location')) {
            $order->update(['delivery_location' => self::location($shipment)]);
        }
        $attributes = ['origin_hub_id' => $shipment->provider->id, 'current_hub_id' => $shipment->currentHubId()];
        if (! $shipment->destination_hub_id) {
            if (self::serves($shipment->provider, self::location($shipment))) {
                $attributes['destination_hub_id'] = $shipment->provider->id;
            } elseif (($hubs = $this->destinations($shipment))->count() === 1) {
                $attributes['destination_hub_id'] = $hubs->first()->id;
            }
        }
        $shipment->update($attributes);
        $shipment->unsetRelation('originHub')->unsetRelation('currentHub')->unsetRelation('destinationHub');
    }

    private function tracking(Shipment $shipment, string $tracking): void
    {
        if (strcasecmp(trim($tracking), $shipment->tracking_number) !== 0) {
            throw ValidationException::withMessages(['tracking' => 'The scanned tracking number does not match this parcel.']);
        }
    }

    public function dispatch(Shipment $shipment, LogisticsProvider $destination, User $actor, string $tracking): void
    {
        abort_unless(self::available(), 503);
        DB::transaction(function () use ($shipment, $destination, $actor, $tracking) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->hasRole('logistics') && $actor->isActive() && (int) $actor->logisticsProvider?->id === $locked->currentHubId(), 403);
            $this->tracking($locked, $tracking);
            if (ShipmentWorkflow::stageOf($locked) !== 'in_transit' || ShipmentWorkflow::riderOf($locked) || $locked->destination_received_at) {
                throw ValidationException::withMessages(['shipment' => 'Only a parcel received at its origin hub and not assigned to a delivery rider can be dispatched.']);
            }
            $this->prepare($locked);
            if (! $this->destinations($locked)->contains('id', $destination->id)) {
                throw ValidationException::withMessages(['destination_hub_id' => 'Choose an active hub in the buyer’s delivery city within the same logistics network.']);
            }
            $origin = $locked->currentHub ?? $locked->provider;
            $locked->update([
                'origin_hub_id' => $origin->id, 'current_hub_id' => $origin->id, 'destination_hub_id' => $destination->id,
                'hub_dispatched_at' => now(), 'hub_dispatched_by' => $actor->id, 'sorted_at' => null, 'sort_area' => null,
            ]);
            app(ShipmentWorkflow::class)->recordHubMovement($locked, $actor, 'Dispatched from '.$origin->name.' to '.$destination->name.'; awaiting destination-hub receipt.');
        });
    }

    public function receive(Shipment $shipment, User $actor, string $tracking): void
    {
        abort_unless(self::available(), 503);
        DB::transaction(function () use ($shipment, $actor, $tracking) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->hasRole('logistics') && $actor->isActive() && (int) $actor->logisticsProvider?->id === (int) $locked->destination_hub_id, 403);
            $this->tracking($locked, $tracking);
            if (! self::inTransfer($locked) || ShipmentWorkflow::stageOf($locked) !== 'hub_transfer') {
                throw ValidationException::withMessages(['shipment' => 'This parcel is not awaiting destination-hub receipt.']);
            }
            $hub = $locked->destinationHub;
            if (! $hub || $hub->status !== 'approved' || ! self::sameNetwork($locked->originHub, $hub)
                || ! self::serves($hub, self::location($locked) ?? [])) {
                throw ValidationException::withMessages(['shipment' => 'The destination hub no longer matches the approved route. Contact the administrator.']);
            }
            $locked->update(['current_hub_id' => $hub->id, 'destination_received_at' => now(), 'destination_received_by' => $actor->id, 'sorted_at' => null, 'sort_area' => null]);
            app(ShipmentWorkflow::class)->recordHubMovement($locked, $actor, 'Received at destination hub '.$hub->name.'. Next: sort for '.self::area($locked).'.');
        });
    }
}
