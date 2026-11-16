<?php

namespace App\Services;

use App\Models\RiderLegCommission;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RiderLegEarnings
{
    /** Integer centavos keep both legs within the funded shipping budget. */
    public static function calculate(float $pickupKm, float $deliveryKm, float $kg, float $shipping, array $rates): array
    {
        foreach ([$pickupKm, $deliveryKm, $kg, $shipping, ...array_values($rates)] as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0) throw new \InvalidArgumentException('Rates, distances and weights must be non-negative.');
        }
        if ($kg <= 0) throw new \InvalidArgumentException('Parcel weight is required.');
        $amount = fn ($km) => (int) round(($rates['base_per_leg'] + $km * $rates['per_km'] + $kg * $rates['per_kg']) * 100);
        $pickup = $amount($pickupKm);
        $delivery = $amount($deliveryKm);
        $budget = (int) floor(round($shipping * 100) * min(100, $rates['shipping_budget_percent']) / 100);
        if ($pickup + $delivery > $budget) {
            $pickup = (int) floor($budget * $pickup / ($pickup + $delivery));
            $delivery = $budget - $pickup;
        }
        return ['policy_version' => 3, 'pickup_km' => round($pickupKm, 3), 'delivery_km' => round($deliveryKm, 3),
            'weight_kg' => round($kg, 3), 'pickup_amount' => $pickup / 100, 'delivery_amount' => $delivery / 100,
            'total' => ($pickup + $delivery) / 100, 'rates' => $rates];
    }

    public function quote(Shipment $shipment): array
    {
        if ($shipment->rider_fee_quote) return $shipment->rider_fee_quote;
        $seller = ParcelTracking::addressPin(RiderSellerPickups::pickupAddress($shipment));
        $hub = ParcelTracking::point($shipment->provider?->hub_latitude, $shipment->provider?->hub_longitude);
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        $buyer = ParcelTracking::destination($order);
        if (! $seller || ! $hub || ! $buyer) {
            $missing = array_filter([! $seller ? 'seller pickup address' : null, ! $hub ? 'logistics hub' : null, ! $buyer ? 'buyer delivery address' : null]);
            throw ValidationException::withMessages(['commission' => 'Commission pending: add map pins for the '.implode(', ', $missing).'.']);
        }
        $items = $shipment->sellerOrder?->items ?? $order?->items ?? collect();
        if ($items->isEmpty() || $items->contains(fn ($item) => ! ($item->variant?->weight_grams ?: $item->product?->weight_grams))) {
            throw ValidationException::withMessages(['commission' => 'Commission pending: the seller must enter the parcel product weights.']);
        }
        $kg = $items->sum(fn ($item) => (int) ($item->variant?->weight_grams ?: $item->product?->weight_grams) * (int) $item->quantity) / 1000;
        $shipping = app(Settlement::class)->forShipment($shipment)['shipping'];
        $quote = self::calculate($this->distance($seller, $hub), $this->distance($hub, $buyer), $kg, (float) $shipping,
            collect(config('rider_fees'))->only(['base_per_leg', 'per_km', 'per_kg', 'shipping_budget_percent'])->all());
        $shipment->update(['rider_fee_quote' => $quote]);
        return $quote;
    }

    /** Collection is independent of financial setup. Never substitute invented distances or weights. */
    public function quoteOrDefer(Shipment $shipment): ?array
    {
        try {
            return $this->quote($shipment);
        } catch (ValidationException|\Illuminate\Http\Client\ConnectionException $error) {
            $reason = $error instanceof ValidationException ? $error->validator->errors()->first()
                : 'Commission pending: the road routing service is temporarily unavailable.';
            app(AccountUpdates::class)->send($shipment->provider?->user_id, 'commission-setup-'.$shipment->id, 'wallet',
                'Parcel commission needs attention', RiderDeliveries::labelCode($shipment).': '.$reason);
            return null;
        }
    }

    private function distance(array $from, array $to): float
    {
        $coordinates = $from['longitude'].','.$from['latitude'].';'.$to['longitude'].','.$to['latitude'];
        return Cache::remember('rider-road-distance:'.hash('sha256', $coordinates), now()->addDays(7), function () use ($coordinates) {
            $response = Http::acceptJson()->timeout(10)->get(rtrim(config('rider_fees.routing_url'), '/').'/route/v1/driving/'.$coordinates,
                ['overview' => 'false', 'steps' => 'false']);
            $meters = $response->json('routes.0.distance');
            if (! $response->successful() || $response->json('code') !== 'Ok' || ! is_numeric($meters) || ! is_finite((float) $meters) || $meters < 0) {
                throw ValidationException::withMessages(['commission' => 'Commission pending: the road distance could not be calculated. It will be retried.']);
            }
            return (float) $meters / 1000;
        });
    }

    public function credit(Shipment $shipment, User $rider, string $leg): ?RiderLegCommission
    {
        if (! Schema::hasTable('rider_leg_commissions')) return null;
        $pickup = $leg === 'pickup';
        if (! in_array($leg, ['pickup', 'delivery'], true)) throw new \InvalidArgumentException('Unknown rider leg.');
        if ($pickup) {
            if (! $shipment->pickup_scanned_at || ! $shipment->hub_received_at || ! $shipment->rider_handover_confirmed_at
                || (int) $shipment->pickup_courier_id !== (int) $rider->id) return null;
        } elseif (ShipmentWorkflow::stageOf($shipment) !== 'delivered' || ! $shipment->delivery_proof_path
            || ! app(RiderDeliveries::class)->owns($shipment, $rider)) return null;
        $quote = $shipment->rider_fee_quote ?: $this->quoteOrDefer($shipment);
        if (! $quote) return null;
        $commission = RiderLegCommission::firstOrCreate(['shipment_id' => $shipment->id, 'leg' => $leg], [
            'user_id' => $rider->id, 'logistics_provider_id' => $shipment->logistics_provider_id,
            'amount' => $quote[$leg.'_amount'], 'distance_km' => $quote[$leg.'_km'], 'weight_kg' => $quote['weight_kg'],
        ]);
        if ($commission->wasRecentlyCreated) app(AccountUpdates::class)->send($rider->id, 'leg-earned-'.$commission->id, 'wallet',
            ucfirst($leg).' commission credited', 'PHP '.number_format($commission->amount, 2).' credited for parcel '.RiderDeliveries::labelCode($shipment).'.');
        return $commission;
    }

    /** Retry completed legs once missing pins/weights or routing become available. Credit remains idempotent. */
    public function reconcile(): int
    {
        if (! Schema::hasTable('rider_leg_commissions')) return 0;
        $credited = 0;
        Shipment::where(function ($query) {
            $query->where(function ($pickup) {
                $pickup->whereNotNull('hub_received_at')->whereNotNull('pickup_scanned_at')->whereNotNull('rider_handover_confirmed_at')
                    ->whereNotExists(fn ($entries) => $entries->selectRaw('1')->from('rider_leg_commissions')
                        ->whereColumn('shipment_id', 'shipments.id')->where('leg', 'pickup'));
            })->orWhere(function ($delivery) {
                $delivery->whereNotNull('delivery_proof_path')->whereNotExists(fn ($entries) => $entries->selectRaw('1')->from('rider_leg_commissions')
                    ->whereColumn('shipment_id', 'shipments.id')->where('leg', 'delivery'));
            });
        })->chunkById(50, function ($shipments) use (&$credited) {
            foreach ($shipments as $shipment) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($shipment, &$credited) {
                    $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
                    if ($rider = $locked->pickupCourier) $credited += (int) ($this->credit($locked, $rider, 'pickup')?->wasRecentlyCreated ?? false);
                    if ($rider = $locked->rider?->user ?? $locked->courier) $credited += (int) ($this->credit($locked, $rider, 'delivery')?->wasRecentlyCreated ?? false);
                });
            }
        });
        return $credited;
    }
}
