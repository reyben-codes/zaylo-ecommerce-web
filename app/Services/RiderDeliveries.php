<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ParcelDeliveryUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The rider app's part of ShipmentWorkflow. A logistics partner collects,
 * sorts and assigns each parcel; the rider then takes it out for delivery
 * and marks it delivered, or reports that it could not be delivered and
 * tries again. Riders cannot claim parcels themselves.
 *
 * Starting a delivery and handing the parcel over both need a scan of the
 * QR code on the parcel label, so the rider has the parcel in hand. Each
 * scan tells the buyer, and the hand-over scan also tells the seller.
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
        'out_for_delivery' => 'Scan to start delivery',
        'delivered' => 'Scan to confirm delivery',
        'failed' => 'Could not deliver',
    ];

    /** Steps the rider can only take by scanning the parcel's QR code. */
    public const SCAN_STEPS = ['out_for_delivery', 'delivered'];

    public function __construct(
        private readonly ShipmentWorkflow $workflow,
        private readonly LogisticsMessenger $messenger,
    ) {}

    /** While sellers deliver their own parcels, riders have nothing to deliver. */
    public static function sellerManaged(): bool
    {
        return (bool) config('marketplace.seller_managed_delivery');
    }

    /** The code printed on the parcel label and encoded in its QR code. */
    public static function labelCode(Shipment $shipment): string
    {
        return (string) ($shipment->tracking_number ?? $shipment->tracking_code ?? '');
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

    /**
     * Finds the rider's parcel by the code scanned from its label and takes its scan step: out for delivery when
     * the rider sets off, delivered at the door.
     *
     * @return array{0: Shipment, 1: string} the parcel, and the step it moved to
     */
    public function scan(User $rider, string $code): array
    {
        abort_if(self::sellerManaged(), 409, 'Sellers are delivering their own parcels right now, so rider updates are turned off.');
        $code = trim($code);
        $shipment = $this->forRider($rider)->where(function (Builder $query) use ($code) {
            $query->whereRaw('1 = 0');
            foreach (['tracking_number', 'tracking_code'] as $column) {
                if (Schema::hasColumn('shipments', $column)) {
                    $query->orWhere($column, $code);
                }
            }
        })->first();
        if (! $shipment) {
            throw ValidationException::withMessages(['code' => 'This parcel is not one of your deliveries. Check the label, or ask your logistics partner.']);
        }

        $stage = ShipmentWorkflow::stageOf($shipment);
        if ($stage === 'delivered') {
            throw ValidationException::withMessages(['code' => 'This parcel was already delivered.']);
        }
        $to = collect(array_keys(self::nextSteps($shipment)))->first(fn (string $step) => in_array($step, self::SCAN_STEPS, true));
        if (! $to) {
            throw ValidationException::withMessages(['code' => 'This parcel cannot be scanned while it is "'.ShipmentWorkflow::label($stage).'". Pull down to refresh.']);
        }

        $shipment = $this->advance($shipment, $rider, $to, scanned: true);
        $this->notifyParties($shipment, $to);

        return [$shipment, $to];
    }

    public function advance(Shipment $shipment, User $rider, string $to, bool $scanned = false): Shipment
    {
        abort_if(self::sellerManaged(), 409, 'Sellers are delivering their own parcels right now, so rider updates are turned off.');
        abort_unless($this->owns($shipment, $rider), 403, 'This delivery is assigned to another rider.');
        if (! array_key_exists($to, self::nextSteps($shipment))) {
            throw ValidationException::withMessages(['status' => 'This delivery can no longer move to "'.ShipmentWorkflow::label($to).'". Pull down to refresh.']);
        }
        if (in_array($to, self::SCAN_STEPS, true) && ! $scanned) {
            throw ValidationException::withMessages(['status' => 'Scan the QR code on the parcel label to continue.']);
        }
        $this->workflow->advance($shipment, $to, $rider);

        return $shipment->fresh();
    }

    /**
     * Emails the buyer that the parcel is on its way or delivered, and the seller once it is delivered; the seller
     * also gets a note in their chat with the logistics partner. Buyers also see each step in their notifications,
     * which come from the order history ShipmentWorkflow records.
     */
    private function notifyParties(Shipment $shipment, string $step): void
    {
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        if (! $order) {
            return;
        }
        $order->buyer?->notify(new ParcelDeliveryUpdate($shipment, $order, $step, forSeller: false));

        if ($step !== 'delivered') {
            return;
        }
        $seller = User::find(ShipmentWorkflow::sellerUserId($shipment));
        if (! $seller) {
            return;
        }
        $seller->notify(new ParcelDeliveryUpdate($shipment, $order, $step, forSeller: true));
        $provider = $shipment->provider ?? User::find($shipment->riderUserId())?->rider?->provider;
        if ($provider) {
            $this->messenger->notify($provider, $seller->id, "Order {$order->order_number} (parcel ".self::labelCode($shipment).') was delivered to the buyer.');
        }
    }
}
