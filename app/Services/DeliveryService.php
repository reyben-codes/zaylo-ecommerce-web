<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rider delivery rules shared by the courier website and the rider app:
 * which deliveries a rider can take, accepting one, and moving it through
 * assigned -> picked_up -> in_transit -> delivered.
 */
class DeliveryService
{
    /** Each status and the one status a rider may move it to next. */
    public const NEXT_STATUS = [
        'assigned' => 'picked_up',
        'picked_up' => 'in_transit',
        'in_transit' => 'delivered',
    ];

    public const FINISHED_STATUSES = ['delivered', 'cancelled'];

    /** Packed by the seller and not yet taken by any rider. */
    public function available(): Builder
    {
        return Shipment::query()->whereNull('courier_id')->where('status', 'ready');
    }

    /** The rider's deliveries that are still in progress. */
    public function active(User $rider): Builder
    {
        return Shipment::query()->where('courier_id', $rider->id)->whereNotIn('status', self::FINISHED_STATUSES);
    }

    public function completed(User $rider): Builder
    {
        return Shipment::query()->where('courier_id', $rider->id)->where('status', 'delivered');
    }

    /** Assigns an available delivery to the rider; only one rider can win it. */
    public function claim(Shipment $shipment, User $rider): Shipment
    {
        return DB::transaction(function () use ($shipment, $rider) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->courier_id || $locked->status !== 'ready') {
                throw ValidationException::withMessages(['delivery' => 'This delivery is no longer available.']);
            }
            $locked->update(['courier_id' => $rider->id, 'status' => 'assigned']);
            $locked->order()->update(['status' => 'assigned']);
            $locked->order->statusHistory()->create([
                'changed_by' => $rider->id, 'status' => 'assigned', 'note' => 'Courier accepted delivery.',
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Moves the rider's delivery to [$status], which must be its next step.
     * Delivering completes the order, marks payment paid, and records the
     * seller's commission.
     */
    public function advance(Shipment $shipment, User $rider, string $status): Shipment
    {
        abort_unless($shipment->courier_id === $rider->id, 403, 'This delivery is assigned to another rider.');

        return DB::transaction(function () use ($shipment, $rider, $status) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ((self::NEXT_STATUS[$locked->status] ?? null) !== $status) {
                throw ValidationException::withMessages(['status' => 'That delivery status transition is not allowed.']);
            }
            $timestamps = match ($status) {
                'picked_up' => ['picked_up_at' => now()],
                'delivered' => ['delivered_at' => now()],
                default => [],
            };
            $locked->update(['status' => $status] + $timestamps);
            $orderStatus = $status === 'delivered' ? 'completed' : $status;
            $locked->order()->update(['status' => $orderStatus] + ($orderStatus === 'completed' ? ['completed_at' => now()] : []));
            $locked->order->statusHistory()->create([
                'changed_by' => $rider->id, 'status' => $orderStatus, 'note' => 'Updated by courier.',
            ]);
            if ($status === 'delivered') {
                $locked->order->payment?->update(['status' => 'paid', 'paid_at' => now()]);
                if ($locked->order->seller_id) {
                    DB::table('commissions')->updateOrInsert(
                        ['order_id' => $locked->order_id],
                        [
                            'seller_id' => $locked->order->seller_id, 'rate' => 10,
                            'amount' => round((float) $locked->order->subtotal * 0.10, 2),
                            'status' => 'earned', 'created_at' => now(), 'updated_at' => now(),
                        ]
                    );
                }
            }

            return $locked->fresh();
        });
    }
}
