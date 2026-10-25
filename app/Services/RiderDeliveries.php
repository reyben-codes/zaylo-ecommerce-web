<?php

namespace App\Services;

use App\Models\DeliveryEvent;
use App\Models\RiderDeliveryLog;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ParcelDeliveryUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * A rider's deliveries in the rider app. The logistics partner assigns parcels to its riders at the hub
 * (ShipmentWorkflow::assign); the rider accepts or declines each one (a declined parcel goes back to the hub for
 * another rider), then takes it out for delivery and marks it delivered or failed with a reason, through the same
 * ShipmentWorkflow the Logistics Center uses, so orders and seller orders update the same way. Only the rider
 * takes these steps; the logistics partner handles returns.
 *
 * Starting a delivery and handing the parcel over both need a scan of the waybill's barcode or QR code, so the
 * rider has the parcel in hand. Each step tells the buyer, and delivering or failing also tells the seller.
 * Every decision is kept in rider_delivery_logs, which the rider's reports are built from.
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

    /** Why a rider turns down an assignment; shown to the logistics partner so it can reassign the parcel. */
    public const DECLINE_REASONS = [
        'too_far' => 'Outside my delivery area',
        'too_many' => 'I have too many parcels',
        'vehicle' => 'Vehicle problem',
        'too_large' => 'Parcel too large or heavy for my vehicle',
        'unavailable' => 'Not available today',
        'other' => 'Other reason',
    ];

    /** Why a delivery attempt failed; told to the buyer and seller, and shown to the logistics partner. */
    public const FAILED_REASONS = [
        'buyer_cancelled' => 'Buyer cancelled the order at delivery',
        'buyer_refused' => 'Buyer refused to accept the parcel',
        'buyer_unavailable' => 'Buyer was not available to receive it',
        'unreachable' => 'Could not reach the buyer by phone',
        'wrong_address' => 'Wrong or incomplete address',
        'no_cash' => 'Buyer could not pay the cash on delivery',
        'rescheduled' => 'Buyer asked to deliver another day',
        'unsafe' => 'Area unsafe or not accessible',
        'other' => 'Other reason',
    ];

    public const STATUS_LABELS = [
        'new' => 'New assignment',
        'assigned' => 'Accepted · pick up at hub',
        'out_for_delivery' => 'On the way',
        'delivered' => 'Delivered',
        'failed' => 'Delivery failed',
        'returned' => 'Returned to seller',
        'cancelled' => 'Cancelled',
    ];

    /** Rider log actions in words, for the delivery timeline. */
    public const LOG_LABELS = [
        RiderDeliveryLog::ACCEPTED => 'You accepted the delivery',
        RiderDeliveryLog::DECLINED => 'You declined the delivery',
        RiderDeliveryLog::OUT_FOR_DELIVERY => 'Out for delivery',
        RiderDeliveryLog::FAILED => 'Delivery attempt failed',
        RiderDeliveryLog::DELIVERED => 'Delivered to the buyer',
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

    /** Every parcel currently or ever assigned to this rider (a declined parcel leaves the rider's list). */
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

    /** Whether rider decisions are recorded; until the 2026_10_25 migration runs, assignments need no acceptance. */
    public static function tracksDecisions(): bool
    {
        return Schema::hasTable('rider_delivery_logs');
    }

    /** An assigned parcel waits for its rider to accept (or decline) before it can go out for delivery. */
    public static function needsAcceptance(Shipment $shipment): bool
    {
        $riderId = $shipment->riderUserId();
        if (! $riderId || ShipmentWorkflow::stageOf($shipment) !== 'assigned' || ! self::tracksDecisions()) {
            return false;
        }
        $logs = $shipment->relationLoaded('riderLogs') ? $shipment->riderLogs : $shipment->riderLogs()->get();

        return ! $logs->contains(fn (RiderDeliveryLog $log) => $log->action === RiderDeliveryLog::ACCEPTED && (int) $log->user_id === $riderId);
    }

    /** The parcel's stage as the rider sees it: an assignment not accepted yet is "new". */
    public static function riderStage(Shipment $shipment): string
    {
        return self::needsAcceptance($shipment) ? 'new' : ShipmentWorkflow::stageOf($shipment);
    }

    /** @return list<string> what this rider can move the parcel to now (nothing until they accept it) */
    public function nextSteps(Shipment $shipment): array
    {
        return self::needsAcceptance($shipment) ? [] : (self::RIDER_STEPS[ShipmentWorkflow::stageOf($shipment)] ?? []);
    }

    /** The rider takes the assignment; they can then pick the parcel up at the hub and start the delivery. */
    public function accept(Shipment $shipment, User $rider): Shipment
    {
        abort_unless($this->owns($shipment, $rider), 403, 'This delivery is assigned to another rider.');
        if (! self::needsAcceptance($shipment)) {
            throw ValidationException::withMessages(['shipment' => 'This delivery was already accepted or is no longer waiting for you. Refresh and try again.']);
        }
        $this->log($shipment, $rider, RiderDeliveryLog::ACCEPTED);

        return $shipment->fresh();
    }

    /**
     * The rider turns the assignment down before setting off: the parcel goes back to the hub's list of parcels
     * waiting for a rider, with the reason, so the logistics partner can give it to someone else.
     */
    public function decline(Shipment $shipment, User $rider, string $reason, ?string $note = null): void
    {
        abort_unless($this->owns($shipment, $rider), 403, 'This delivery is assigned to another rider.');
        if (ShipmentWorkflow::stageOf($shipment) !== 'assigned') {
            throw ValidationException::withMessages(['shipment' => 'You can only decline a parcel before you take it out for delivery.']);
        }
        if (! isset(self::DECLINE_REASONS[$reason])) {
            throw ValidationException::withMessages(['reason' => 'Choose why you are declining this delivery.']);
        }
        $this->workflow->unassign($shipment);
        $this->log($shipment, $rider, RiderDeliveryLog::DECLINED, $reason, $note);
    }

    /** The most recent decline of a parcel, for the logistics partner deciding who gets it next. */
    public static function lastDecline(Shipment $shipment): ?RiderDeliveryLog
    {
        if (! self::tracksDecisions()) {
            return null;
        }

        return $shipment->riderLogs()->where('action', RiderDeliveryLog::DECLINED)->with('user')->reorder()->latest('id')->first();
    }

    /** The reason behind a decline or a failed attempt, in words. */
    public static function reasonLabel(?string $action, ?string $reason): ?string
    {
        if (! $reason) {
            return null;
        }

        return ($action === RiderDeliveryLog::DECLINED ? self::DECLINE_REASONS : self::FAILED_REASONS)[$reason] ?? null;
    }

    /** "Buyer was not available to receive it (gate was locked)": the reason and the rider's own words. */
    public static function failureSummary(?string $reason, ?string $note): string
    {
        $note = trim((string) $note);
        $label = self::FAILED_REASONS[$reason ?? ''] ?? 'Delivery could not be completed';

        return $note === '' ? $label : $label.' ('.$note.')';
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
        if (self::needsAcceptance($shipment)) {
            throw ValidationException::withMessages(['code' => 'Accept this delivery first, then scan it again to start.']);
        }
        $to = collect($this->nextSteps($shipment))->first(fn (string $step) => in_array($step, self::SCAN_STEPS, true));
        if (! $to) {
            throw ValidationException::withMessages(['code' => 'This parcel cannot be scanned while it is "'.(self::STATUS_LABELS[$stage] ?? ShipmentWorkflow::label($stage)).'". Refresh and try again.']);
        }

        return [$this->advance($shipment, $rider, $to, scanned: true), $to];
    }

    /**
     * Takes a step on the rider's parcel. A failed attempt needs one of FAILED_REASONS; the buyer and seller are
     * told why. Every step is logged for the rider's reports and added to the buyer's order updates.
     */
    public function advance(Shipment $shipment, User $rider, string $to, bool $scanned = false, ?string $reason = null, ?string $note = null): Shipment
    {
        abort_unless($this->owns($shipment, $rider), 403, 'This delivery is assigned to another rider.');
        if (self::needsAcceptance($shipment)) {
            throw ValidationException::withMessages(['status' => 'Accept this delivery before you start it.']);
        }
        if (! in_array($to, $this->nextSteps($shipment), true)) {
            throw ValidationException::withMessages(['status' => 'This delivery can no longer move to "'.(self::STATUS_LABELS[$to] ?? $to).'". Refresh and try again.']);
        }
        if (in_array($to, self::SCAN_STEPS, true) && ! $scanned) {
            throw ValidationException::withMessages(['status' => 'Scan the QR code or barcode on the waybill to continue.']);
        }
        if ($to === 'failed' && ! isset(self::FAILED_REASONS[$reason ?? ''])) {
            throw ValidationException::withMessages(['reason' => 'Choose why the parcel could not be delivered.']);
        }
        $failure = $to === 'failed' ? self::failureSummary($reason, $note) : null;
        $this->workflow->advance($shipment, $to, $rider, $failure ? 'Delivery attempt failed: '.$failure.'.' : null);
        $shipment = $shipment->fresh();

        $this->log($shipment, $rider, $to, $failure ? $reason : null, $failure ? $note : null);
        $this->recordBuyerUpdate($shipment, $rider, $to, $failure);
        $this->notifyParties($shipment, $to, $rider, $failure);

        return $shipment;
    }

    /**
     * What the rider did, for one period (all time when [$since] is null): assignments accepted and declined,
     * deliveries and failed attempts with their reasons, the success rate, cash collected and delivery fees,
     * and the last seven days day by day.
     */
    public function report(User $rider, ?Carbon $since, string $timezone): array
    {
        $delivered = $this->completedSince($rider, $since)->get();
        $logs = self::tracksDecisions()
            ? RiderDeliveryLog::query()->where('user_id', $rider->id)
                ->when($since, fn (Builder $query) => $query->where('created_at', '>=', $since->copy()->utc()))
                ->get(['action', 'reason', 'created_at'])
            : collect();
        $failed = $logs->where('action', RiderDeliveryLog::FAILED);
        $declined = $logs->where('action', RiderDeliveryLog::DECLINED);
        $attempts = $delivered->count() + $failed->count();
        $breakdown = fn ($entries, array $labels) => $entries->groupBy('reason')
            ->map(fn ($group, $reason) => ['reason' => $reason, 'label' => $labels[$reason] ?? 'Other reason', 'count' => $group->count()])
            ->sortByDesc('count')->values();

        $today = now($timezone)->startOfDay();
        $allDelivered = $since ? $this->completed($rider)->get() : $delivered;
        $recentFailed = self::tracksDecisions()
            ? RiderDeliveryLog::query()->where('user_id', $rider->id)->where('action', RiderDeliveryLog::FAILED)
                ->where('created_at', '>=', $today->copy()->subDays(6)->utc())->get(['created_at'])
            : collect();
        $days = collect(range(6, 0))->map(function (int $daysAgo) use ($today, $allDelivered, $recentFailed, $timezone) {
            $start = $today->copy()->subDays($daysAgo);
            $end = $start->copy()->addDay();
            $inDay = fn (?Carbon $at) => $at && $at->gte($start) && $at->lt($end);

            return [
                'date' => $start->toDateString(),
                'delivered' => $allDelivered->filter(fn (Shipment $s) => $inDay(self::deliveredAt($s)?->timezone($timezone)))->count(),
                'failed' => $recentFailed->filter(fn (RiderDeliveryLog $log) => $inDay($log->created_at?->timezone($timezone)))->count(),
            ];
        });

        return [
            'assignments' => [
                'accepted' => $logs->where('action', RiderDeliveryLog::ACCEPTED)->count(),
                'declined' => $declined->count(),
            ],
            'deliveries' => [
                'delivered' => $delivered->count(),
                'failed_attempts' => $failed->count(),
                // Delivered parcels out of every attempt that ended (delivered or failed).
                'success_rate' => $attempts ? round($delivered->count() / $attempts * 100, 1) : null,
            ],
            'money' => [
                'cash_collected' => round($delivered->sum(fn (Shipment $s) => self::amounts($s)['cash_to_collect']), 2),
                'delivery_fees' => round($delivered->sum(fn (Shipment $s) => self::amounts($s)['delivery_fee']), 2),
            ],
            'failed_reasons' => $breakdown($failed, self::FAILED_REASONS),
            'declined_reasons' => $breakdown($declined, self::DECLINE_REASONS),
            'last_7_days' => $days->values(),
        ];
    }

    /** Everything the rider did with this parcel, oldest first, for the delivery's details screen. */
    public static function timeline(Shipment $shipment, string $timezone): array
    {
        if (! self::tracksDecisions()) {
            return [];
        }

        return $shipment->riderLogs()->get()->map(fn (RiderDeliveryLog $log) => [
            'action' => $log->action,
            'label' => self::LOG_LABELS[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)),
            'reason' => self::reasonLabel($log->action, $log->reason),
            'note' => $log->note,
            'at' => $log->created_at?->timezone($timezone)->toIso8601String(),
        ])->values()->all();
    }

    private function log(Shipment $shipment, User $rider, string $action, ?string $reason = null, ?string $note = null): void
    {
        if (! self::tracksDecisions()) {
            return;
        }
        $note = trim((string) $note);
        RiderDeliveryLog::create([
            'shipment_id' => $shipment->id, 'user_id' => $rider->id, 'action' => $action,
            'reason' => $reason, 'note' => $note === '' ? null : mb_substr($note, 0, 500),
        ]);
    }

    /**
     * Adds the step to the buyer's order updates on the newer schema, which keeps them as delivery events (the
     * deployed schema gets them through ShipmentWorkflow's order history).
     */
    private function recordBuyerUpdate(Shipment $shipment, User $rider, string $step, ?string $failure): void
    {
        if (! Schema::hasTable('delivery_events') || ! Schema::hasColumn('shipments', 'seller_order_id')) {
            return;
        }
        DeliveryEvent::create([
            'shipment_id' => $shipment->id, 'status' => $step, 'attempt' => max(1, (int) $shipment->attempts),
            'user_id' => $rider->id, 'note' => $failure ? 'Delivery attempt failed: '.$failure.'.' : null, 'occurred_at' => now(),
        ]);
    }

    /**
     * Emails the buyer that the parcel is on its way, delivered, or could not be delivered (with the reason), and
     * the seller once it is delivered or an attempt failed; the seller also gets a note in their chat with the
     * logistics partner.
     */
    private function notifyParties(Shipment $shipment, string $step, User $rider, ?string $failure = null): void
    {
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        if (! $order) {
            return;
        }
        $order->buyer?->notify(new ParcelDeliveryUpdate($shipment, $order, $step, forSeller: false, reason: $failure));

        if (! in_array($step, ['delivered', 'failed'], true)) {
            return;
        }
        $seller = User::find(ShipmentWorkflow::sellerUserId($shipment));
        if (! $seller) {
            return;
        }
        $seller->notify(new ParcelDeliveryUpdate($shipment, $order, $step, forSeller: true, reason: $failure));
        $provider = $shipment->provider ?? $rider->rider?->provider;
        if ($provider) {
            $number = $order->order_number ?? $order->reference;
            $parcel = self::labelCode($shipment);
            $this->messenger->notify($provider, $seller->id, $step === 'delivered'
                ? "Order {$number} (parcel {$parcel}) was delivered to the buyer."
                : "Order {$number} (parcel {$parcel}) could not be delivered: {$failure}.");
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
