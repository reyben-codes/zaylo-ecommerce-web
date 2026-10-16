<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The route a parcel takes through the logistics partner, and the order status buyers and sellers see at each step.
 *
 * "confirmed" and "sorted" are sorting-center steps kept in their own columns (pickup_confirmed_at, sorted_at),
 * so the parcel status itself stays one the rest of the marketplace already understands.
 */
class ShipmentWorkflow
{
    /** Step => label, in the order a parcel normally moves. */
    public const STAGES = [
        'ready' => 'Pickup requested',
        'confirmed' => 'Pickup confirmed',
        'picked_up' => 'Picked up from seller',
        'in_transit' => 'At hub, to sort',
        'sorted' => 'Sorted',
        'assigned' => 'Rider assigned',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'failed' => 'Delivery failed',
        'returned' => 'Returned to seller',
        'cancelled' => 'Cancelled',
    ];

    /** Steps stored in their own column on top of a parcel status. */
    private const SUB_STEPS = [
        'confirmed' => ['status' => 'ready', 'column' => 'pickup_confirmed_at'],
        'sorted' => ['status' => 'in_transit', 'column' => 'sorted_at'],
    ];

    /** What the step buttons can do next (pickup confirmation, sorting and rider assignment are their own actions). */
    private const NEXT = [
        'ready' => [],
        'confirmed' => ['picked_up'],
        'picked_up' => ['in_transit'],
        'in_transit' => [],
        'sorted' => [],
        'assigned' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'failed'],
        'failed' => ['out_for_delivery', 'returned'],
    ];

    public const ACTION_LABELS = [
        'picked_up' => 'Mark picked up',
        'in_transit' => 'Receive at hub',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Mark delivered',
        'failed' => 'Mark failed',
        'returned' => 'Return to seller',
    ];

    /** Steps of a parcel that is still on its way. */
    public const ACTIVE = ['ready', 'confirmed', 'picked_up', 'in_transit', 'sorted', 'assigned', 'out_for_delivery', 'failed'];

    /** Order status (deployed schema) at each parcel step; failures and returns keep the order where it was. */
    private const ORDER_STATUS = [
        'ready' => 'ready_for_pickup',
        'confirmed' => 'ready_for_pickup',
        'picked_up' => 'picked_up',
        'in_transit' => 'in_transit',
        'sorted' => 'in_transit',
        'assigned' => 'in_transit',
        'out_for_delivery' => 'out_for_delivery',
        'delivered' => 'completed',
    ];

    /** Seller order status (newer schema) at each parcel step. */
    private const SELLER_ORDER_STATUS = [
        'picked_up' => 'shipped',
        'in_transit' => 'shipped',
        'sorted' => 'shipped',
        'assigned' => 'shipped',
        'out_for_delivery' => 'shipped',
        'delivered' => 'delivered',
    ];

    /** Whether this database has the sorting-center columns (added by the 2026_10_15 migration). */
    public static function hasHubSteps(): bool
    {
        return once(fn () => Schema::hasColumn('shipments', 'pickup_confirmed_at') && Schema::hasColumn('shipments', 'sorted_at'));
    }

    /**
     * Other names a status is stored under. The deployed schema stores "ready" when a seller hands a parcel over
     * (older sorting-center code used "unassigned" for the hub); the newer schema's status list has no "ready",
     * so a new, uncollected parcel there is "unassigned".
     */
    public static function aliases(): array
    {
        return Order::usesLegacySchema()
            ? ['unassigned' => 'in_transit', 'pending' => 'ready']
            : ['unassigned' => 'ready'];
    }

    /** The status value saved for a step on this schema. */
    public static function stored(string $stage): string
    {
        $status = self::SUB_STEPS[$stage]['status'] ?? $stage;

        return $status === 'ready' && ! Order::usesLegacySchema() ? 'unassigned' : $status;
    }

    /** The step for a bare status value (sorting-center steps need the parcel; see stageOf). */
    public static function stage(?string $status): string
    {
        return self::aliases()[$status] ?? ($status ?: 'ready');
    }

    public static function stageOf(Shipment $shipment): string
    {
        $stage = self::stage($shipment->status);
        if (self::hasHubSteps()) {
            foreach (self::SUB_STEPS as $subStep => $rule) {
                if ($stage === $rule['status'] && $shipment->{$rule['column']}) {
                    return $subStep;
                }
            }
        }

        return $stage;
    }

    public static function label(?string $stage): string
    {
        return self::STAGES[$stage] ?? self::STAGES[self::stage($stage)] ?? ucfirst(str_replace('_', ' ', (string) $stage));
    }

    public static function labelOf(Shipment $shipment): string
    {
        return self::label(self::stageOf($shipment));
    }

    /** Limits a parcel query to the given steps. */
    public static function whereStage(Builder $query, array $stages): Builder
    {
        $hub = self::hasHubSteps();

        return $query->where(function (Builder $any) use ($stages, $hub) {
            $any->whereRaw('1 = 0');
            foreach ($stages as $stage) {
                $any->orWhere(function (Builder $one) use ($stage, $hub) {
                    if (isset(self::SUB_STEPS[$stage])) {
                        $rule = self::SUB_STEPS[$stage];
                        $hub
                            ? $one->whereIn('status', self::statusesFor([$rule['status']]))->whereNotNull($rule['column'])
                            : $one->whereRaw('1 = 0');

                        return;
                    }
                    $one->whereIn('status', self::statusesFor([$stage]));
                    foreach (self::SUB_STEPS as $rule) {
                        if ($hub && $rule['status'] === $stage) {
                            $one->whereNull($rule['column']);
                        }
                    }
                });
            }
        });
    }

    /** Status values (including older names) behind the given steps. */
    public static function statusesFor(array $stages): array
    {
        return array_values(array_unique(array_merge(
            array_map([self::class, 'stored'], $stages),
            array_keys(array_filter(self::aliases(), fn ($stage) => in_array($stage, $stages, true))),
        )));
    }

    /** @return array<string,string> next status => button label */
    public static function nextSteps(Shipment $shipment): array
    {
        $stage = self::stageOf($shipment);
        // Without the sorting-center columns, a requested pickup is collected directly.
        $next = $stage === 'ready' && ! self::hasHubSteps() ? ['picked_up'] : (self::NEXT[$stage] ?? []);

        return collect($next)->mapWithKeys(fn (string $status) => [$status => self::ACTION_LABELS[$status]])->all();
    }

    public static function awaitingPickupConfirmation(Shipment $shipment): bool
    {
        return self::hasHubSteps() && self::stageOf($shipment) === 'ready';
    }

    public static function awaitingSort(Shipment $shipment): bool
    {
        return self::hasHubSteps() && self::stageOf($shipment) === 'in_transit';
    }

    /** The step at which a parcel is handed to a rider. */
    public static function assignableStage(): string
    {
        return self::hasHubSteps() ? 'sorted' : 'in_transit';
    }

    public static function awaitingRider(Shipment $shipment): bool
    {
        return self::stageOf($shipment) === self::assignableStage() && ! self::riderOf($shipment);
    }

    public static function riderOf(Shipment $shipment): ?int
    {
        return Schema::hasColumn('shipments', 'rider_id') ? $shipment->rider_id : $shipment->courier_id;
    }

    /** The seller's user account, for messages about this parcel. */
    public static function sellerUserId(Shipment $shipment): ?int
    {
        if (Schema::hasColumn('shipments', 'seller_order_id') && $shipment->sellerOrder) {
            return $shipment->sellerOrder->seller?->user_id;
        }

        return $shipment->order?->seller_id;
    }

    /** "City, Province" from the delivery address, to suggest a sorting area. */
    public static function suggestedArea(Shipment $shipment): string
    {
        $address = ($shipment->order ?: $shipment->sellerOrder?->order)?->shipping_address;
        if (is_array($address)) {
            return collect([data_get($address, 'city'), data_get($address, 'province')])->filter()->implode(', ');
        }

        // The deployed schema stores "line1, [line2,] barangay, city, province, [region,] postal code" as text.
        $parts = collect(explode(',', (string) $address))->map(fn ($part) => trim($part))->filter()->values();
        if ($parts->isNotEmpty() && preg_match('/^\d{4}$/', $parts->last())) {
            $parts->pop();
        }
        if ($parts->count() > 3 && self::looksLikeRegion($parts->last())) {
            $parts->pop();
        }

        return $parts->count() >= 2 ? $parts->slice(-2)->implode(', ') : (string) $parts->last();
    }

    private static function looksLikeRegion(string $part): bool
    {
        return (bool) preg_match('/^(NCR|CAR|BARMM|MIMAROPA|CALABARZON|SOCCSKSARGEN|Caraga|Region\b|National Capital|Cordillera|Bangsamoro|Ilocos|Cagayan Valley|Central Luzon|Bicol|Western Visayas|Central Visayas|Eastern Visayas|Zamboanga Peninsula|Northern Mindanao|Davao)/i', $part);
    }

    public function confirmPickup(Shipment $shipment, Carbon $date, User $actor): void
    {
        DB::transaction(function () use ($shipment, $date, $actor) {
            $locked = $this->lockAt($shipment, ['ready', 'confirmed'], 'This pickup was already collected or declined.');
            $locked->update(['pickup_confirmed_at' => $locked->pickup_confirmed_at ?? now(), 'pickup_scheduled_for' => $date->toDateString()]);
            $this->syncOrder($locked, 'confirmed', $actor, 'Pickup confirmed by logistics for '.$date->format('M j, Y').'.');
        });
    }

    /** Hands the order back to the seller to fix and request again; the parcel record is removed. */
    public function declinePickup(Shipment $shipment, string $reason, User $actor): void
    {
        DB::transaction(function () use ($shipment, $reason, $actor) {
            $locked = $this->lockAt($shipment, ['ready', 'confirmed'], 'This pickup was already collected.');
            $order = $locked->order ?: $locked->sellerOrder?->order;
            $locked->sellerOrder?->update(['status' => 'packed']);
            if ($order && Order::usesLegacySchema()) {
                $order->update(['status' => 'processing']);
            }
            if ($order && Schema::hasTable('order_status_histories')) {
                $order->statusHistory()->create([
                    'changed_by' => $actor->id,
                    'status' => Order::usesLegacySchema() ? 'processing' : 'packed',
                    'note' => 'Pickup declined by logistics: '.$reason,
                ]);
            }
            $locked->delete();
        });
    }

    public function sort(Shipment $shipment, string $area, User $actor): void
    {
        DB::transaction(function () use ($shipment, $area, $actor) {
            $locked = $this->lockAt($shipment, ['in_transit', 'sorted'], 'Only parcels at the hub without a rider can be sorted.');
            $locked->update(['sorted_at' => now(), 'sort_area' => $area]);
            $this->syncOrder($locked, 'sorted', $actor, 'Sorted at the hub for '.$area.'.');
        });
    }

    public function advance(Shipment $shipment, string $to, User $actor): void
    {
        DB::transaction(function () use ($shipment, $to, $actor) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if (! array_key_exists($to, self::nextSteps($locked))) {
                throw ValidationException::withMessages(['status' => 'This parcel can no longer move to "'.self::label($to).'". Refresh the page and try again.']);
            }
            $attributes = ['status' => self::stored($to)];
            // Only the deployed schema keeps step timestamps; the newer one counts attempts and COD instead.
            if ($to === 'picked_up' && Schema::hasColumn('shipments', 'picked_up_at') && ! $locked->picked_up_at) {
                $attributes['picked_up_at'] = now();
            }
            if ($to === 'delivered' && Schema::hasColumn('shipments', 'delivered_at')) {
                $attributes['delivered_at'] = now();
            }
            if ($to === 'delivered' && Schema::hasColumn('shipments', 'cod_collected')) {
                $attributes['cod_collected'] = true;
            }
            if ($to === 'out_for_delivery' && Schema::hasColumn('shipments', 'attempts')) {
                $attributes['attempts'] = (int) $locked->attempts + 1;
            }
            $locked->update($attributes);
            $this->syncOrder($locked, $to, $actor);
        });
    }

    public function assign(Shipment $shipment, Rider $rider, User $actor): void
    {
        DB::transaction(function () use ($shipment, $rider, $actor) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if (! self::awaitingRider($locked)) {
                throw ValidationException::withMessages(['shipment' => 'This parcel is no longer waiting for a rider.']);
            }
            // The deployed schema assigns through courier_id (the rider's user); the newer one through rider_id.
            $locked->update((Schema::hasColumn('shipments', 'rider_id') ? ['rider_id' => $rider->id] : ['courier_id' => $rider->user_id]) + ['status' => 'assigned']);
            $this->syncOrder($locked, 'assigned', $actor, 'Assigned to rider '.($rider->user?->name ?? '#'.$rider->id).'.');
        });
    }

    private function lockAt(Shipment $shipment, array $stages, string $message): Shipment
    {
        $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->first();
        if (! $locked || ! in_array(self::stageOf($locked), $stages, true)) {
            throw ValidationException::withMessages(['shipment' => $message]);
        }

        return $locked;
    }

    private function syncOrder(Shipment $shipment, string $stage, User $actor, ?string $note = null): void
    {
        $note ??= 'Parcel '.mb_strtolower(self::label($stage)).' (logistics).';
        $order = $shipment->order ?: $shipment->sellerOrder?->order;

        if ($shipment->sellerOrder && isset(self::SELLER_ORDER_STATUS[$stage])) {
            $shipment->sellerOrder->update(['status' => self::SELLER_ORDER_STATUS[$stage]]);
        }

        if ($order && Order::usesLegacySchema() && isset(self::ORDER_STATUS[$stage])) {
            $status = self::ORDER_STATUS[$stage];
            $order->update(['status' => $status] + ($status === 'completed' ? ['completed_at' => now()] : []));
            if ($status === 'completed') {
                $order->payment?->update(['status' => 'paid', 'paid_at' => now()]);
                if ($order->seller_id && Schema::hasTable('commissions')) {
                    DB::table('commissions')->updateOrInsert(['order_id' => $order->id], [
                        'seller_id' => $order->seller_id, 'rate' => 10,
                        'amount' => round((float) $order->subtotal * 0.10, 2),
                        'status' => 'earned', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        if ($order && Schema::hasTable('order_status_histories')) {
            $order->statusHistory()->create([
                'changed_by' => $actor->id,
                'status' => self::ORDER_STATUS[$stage] ?? $stage,
                'note' => $note,
            ]);
        }
    }
}
