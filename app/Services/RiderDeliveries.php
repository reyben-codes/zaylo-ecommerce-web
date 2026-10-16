<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The rider app's part of ShipmentWorkflow. A logistics partner collects,
 * sorts and assigns each parcel; the rider then takes it out for delivery
 * and marks it delivered, or reports that it could not be delivered and
 * tries again. Riders cannot claim parcels themselves.
 */
class RiderDeliveries
{
    /** Steps while the parcel is with the rider. */
    public const ACTIVE_STAGES = ['assigned', 'out_for_delivery', 'failed'];

    /** What the rider may do at each step (returning a parcel is the logistics partner's call). */
    private const RIDER_STEPS = [
        'assigned' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'failed'],
        'failed' => ['out_for_delivery'],
    ];

    public const ACTION_LABELS = [
        'out_for_delivery' => 'Start delivery',
        'delivered' => 'Mark as delivered',
        'failed' => 'Could not deliver',
    ];

    public function __construct(private readonly ShipmentWorkflow $workflow) {}

    /** While sellers deliver their own parcels, riders have nothing to deliver. */
    public static function sellerManaged(): bool
    {
        return (bool) config('marketplace.seller_managed_delivery');
    }

    /** Parcels assigned to this rider, at any step. */
    public function forRider(User $rider): Builder
    {
        // The deployed schema assigns through courier_id (the rider's user); the newer one through rider_id.
        return Schema::hasColumn('shipments', 'rider_id')
            ? Shipment::query()->where('rider_id', $rider->rider?->id ?? 0)
            : Shipment::query()->where('courier_id', $rider->id);
    }

    public function active(User $rider): Builder
    {
        return ShipmentWorkflow::whereStage($this->forRider($rider), self::ACTIVE_STAGES);
    }

    public function completed(User $rider): Builder
    {
        return ShipmentWorkflow::whereStage($this->forRider($rider), ['delivered']);
    }

    public function owns(Shipment $shipment, User $rider): bool
    {
        return $shipment->riderUserId() === $rider->id;
    }

    /** @return array<string,string> next step => button label, primary first */
    public static function nextSteps(Shipment $shipment): array
    {
        $allowed = self::RIDER_STEPS[ShipmentWorkflow::stageOf($shipment)] ?? [];
        $possible = array_keys(ShipmentWorkflow::nextSteps($shipment));

        return collect($allowed)->filter(fn (string $step) => in_array($step, $possible, true))
            ->mapWithKeys(fn (string $step) => [$step => self::ACTION_LABELS[$step]])->all();
    }

    public function advance(Shipment $shipment, User $rider, string $to): Shipment
    {
        abort_if(self::sellerManaged(), 409, 'Sellers are delivering their own parcels right now, so rider updates are turned off.');
        abort_unless($this->owns($shipment, $rider), 403, 'This delivery is assigned to another rider.');
        if (! array_key_exists($to, self::nextSteps($shipment))) {
            throw ValidationException::withMessages(['status' => 'This delivery can no longer move to "'.ShipmentWorkflow::label($to).'". Pull down to refresh.']);
        }
        $this->workflow->advance($shipment, $to, $rider);

        return $shipment->fresh();
    }
}
