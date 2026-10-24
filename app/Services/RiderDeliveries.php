<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ParcelDeliveryUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * A rider's deliveries in the rider app. The logistics partner assigns parcels to its riders at the hub
 * (ShipmentWorkflow::assign); the rider then takes each one out for delivery and marks it delivered or failed,
 * through the same ShipmentWorkflow the Logistics Center uses, so orders and seller orders update the same way.
 *
 * Starting a delivery and handing the parcel over both need a scan of the waybill's barcode or QR code, so the
 * rider has the parcel in hand. Each scan tells the buyer, and the hand-over scan also tells the seller.
 */
class RiderDeliveries
{
    /** Steps the rider can only take by scanning the parcel's QR code. */
    public const SCAN_STEPS = ['out_for_delivery', 'delivered'];

    /** What a rider may do next at each step (returning a parcel to the seller stays with the hub). */
    public const RIDER_STEPS = [
        'assigned' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'failed'],
        'failed' => ['out_for_delivery'],
    ];

    /** Stages while the parcel is with the rider; the rider–buyer chat is open only then. */
    public const ACTIVE_STAGES = ['assigned', 'out_for_delivery', 'failed'];

    public const ACTION_LABELS = [
        'out_for_delivery' => 'Scan to start delivery',
        'delivered' => 'Scan to confirm delivery',
        'failed' => 'Mark delivery failed',
    ];

    public const STATUS_LABELS = [
        'assigned' => 'Assigned to you',
        'out_for_delivery' => 'On the way',
        'delivered' => 'Delivered',
        'failed' => 'Delivery failed',
        'returned' => 'Returned to seller',
        'cancelled' => 'Cancelled',
    ];

    public function __construct(
        private readonly ShipmentWorkflow $workflow,
        private readonly LogisticsMessenger $messenger,
    ) {}

    /** The tracking number printed on the waybill and encoded in its barcode and QR code. */
    public static function labelCode(Shipment $shipment): string
    {
        return (string) ($shipment->tracking_number ?? $shipment->tracking_code ?? '');
    }

    /** Every parcel ever assigned to this rider. */
    public function forRider(User $rider): Builder
    {
        return Schema::hasColumn('shipments', 'rider_id')
            ? Shipment::query()->where('rider_id', $rider->rider?->id ?? 0)
            : Shipment::query()->where('courier_id', $rider->id);
    }

    public function active(User $rider): Builder
    {
        return ShipmentWorkflow::whereStage($this->forRider($rider), array_keys(self::RIDER_STEPS));
    }

    public function completed(User $rider): Builder
    {
        return ShipmentWorkflow::whereStage($this->forRider($rider), ['delivered']);
    }

    /** Delivered parcels since a moment (all time when null). */
    public function completedSince(User $rider, ?Carbon $since): Builder
    {
        return $this->completed($rider)->when($since, fn (Builder $query) => $query->where(self::deliveredColumn(), '>=', $since->copy()->utc()));
    }

    public function owns(Shipment $shipment, User $rider): bool
    {
        return $this->forRider($rider)->whereKey($shipment->id)->exists();
    }

    /** @return list<string> what this rider can move the parcel to now */
    public function nextSteps(Shipment $shipment): array
    {
        return self::RIDER_STEPS[ShipmentWorkflow::stageOf($shipment)] ?? [];
    }

    /**
     * Finds the rider's parcel by the code scanned from its label and takes its scan step: out for delivery when
     * the rider sets off, delivered at the door.
     *
     * @return array{0: Shipment, 1: string} the parcel, and the step it moved to
     */
    public function scan(User $rider, string $code): array
    {
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
        $to = collect($this->nextSteps($shipment))->first(fn (string $step) => in_array($step, self::SCAN_STEPS, true));
        if (! $to) {
            throw ValidationException::withMessages(['code' => 'This parcel cannot be scanned while it is "'.(self::STATUS_LABELS[$stage] ?? ShipmentWorkflow::label($stage)).'". Refresh and try again.']);
        }

        $shipment = $this->advance($shipment, $rider, $to, scanned: true);
        $this->notifyParties($shipment, $to, $rider);

        return [$shipment, $to];
    }

    public function advance(Shipment $shipment, User $rider, string $to, bool $scanned = false): Shipment
    {
        abort_unless($this->owns($shipment, $rider), 403, 'This delivery is assigned to another rider.');
        if (! in_array($to, $this->nextSteps($shipment), true)) {
            throw ValidationException::withMessages(['status' => 'This delivery can no longer move to "'.(self::STATUS_LABELS[$to] ?? $to).'". Refresh and try again.']);
        }
        if (in_array($to, self::SCAN_STEPS, true) && ! $scanned) {
            throw ValidationException::withMessages(['status' => 'Scan the QR code or barcode on the waybill to continue.']);
        }
        $this->workflow->advance($shipment, $to, $rider);

        return $shipment->fresh();
    }

    /**
     * Emails the buyer that the parcel is on its way or delivered, and the seller once it is delivered; the seller
     * also gets a note in their chat with the logistics partner. Buyers also see each step in their order history,
     * which ShipmentWorkflow records.
     */
    private function notifyParties(Shipment $shipment, string $step, User $rider): void
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
        $provider = $shipment->provider ?? $rider->rider?->provider;
        if ($provider) {
            $this->messenger->notify($provider, $seller->id, "Order {$order->order_number} (parcel ".self::labelCode($shipment).') was delivered to the buyer.');
        }
    }

    /** Only the deployed schema keeps a delivered_at timestamp; the newer one is dated by its last update. */
    public static function deliveredColumn(): string
    {
        return Schema::hasColumn('shipments', 'delivered_at') ? 'delivered_at' : 'updated_at';
    }

    public static function deliveredAt(Shipment $shipment): ?Carbon
    {
        if (ShipmentWorkflow::stageOf($shipment) !== 'delivered') {
            return null;
        }

        return $shipment->delivered_at ?? $shipment->updated_at;
    }

    /** Order total, cash to collect and the delivery fee, on whichever schema this database uses. */
    public static function amounts(Shipment $shipment): array
    {
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        $sellerOrder = $shipment->sellerOrder;

        // On the newer schema one order can hold several sellers' parcels, so each parcel uses its seller order.
        $total = $sellerOrder
            ? ((int) $sellerOrder->subtotal_minor + (int) $sellerOrder->shipping_fee_minor) / 100
            : (float) ($order?->total ?? 0);
        $fee = match (true) {
            (bool) $shipment->getAttribute('fee_minor') => $shipment->fee_minor / 100,
            $sellerOrder !== null => (int) $sellerOrder->shipping_fee_minor / 100,
            default => (float) ($order?->getAttribute('shipping_fee') ?? 0),
        };
        $cod = $order?->payment_method === 'cod';
        $codAmount = $shipment->getAttribute('cod_amount_minor') ? $shipment->cod_amount_minor / 100 : $total;

        return [
            'total' => round($total, 2),
            'delivery_fee' => round((float) $fee, 2),
            'payment_method' => $order?->payment_method,
            'cash_to_collect' => $cod ? round((float) $codAmount, 2) : 0.0,
        ];
    }
}
