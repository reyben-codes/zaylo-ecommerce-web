<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\RiderDeliveryLog;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Records COD cash at the buyer's door after a rider has completed delivery. */
class CodCollections
{
    public function __construct(
        private readonly RiderDeliveries $deliveries,
        private readonly RiderWallet $wallet,
        private readonly CodFinancialHistory $history,
    ) {}

    public static function ready(): bool
    {
        return Schema::hasColumn('shipments', 'cod_collected')
            && Schema::hasColumn('shipments', 'cod_collected_at')
            && Schema::hasColumn('shipments', 'cod_collected_by');
    }

    public function collect(Shipment $shipment, User $rider): Shipment
    {
        abort_unless($this->deliveries->owns($shipment, $rider), 403, 'This delivery is assigned to another rider.');
        abort_unless(self::ready(), 503, 'COD collection tracking is being set up. Please try again after the database update.');

        return DB::transaction(function () use ($shipment, $rider) {
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->deliveries->owns($locked, $rider), 403, 'This delivery is assigned to another rider.');
            $order = $locked->order ?: $locked->sellerOrder?->order;
            if (! $order || $order->payment_method !== 'cod') {
                throw ValidationException::withMessages(['shipment' => 'This delivery is not a cash-on-delivery order.']);
            }
            if (ShipmentWorkflow::stageOf($locked) !== 'delivered') {
                throw ValidationException::withMessages(['shipment' => 'Mark the parcel delivered before recording its COD collection.']);
            }
            if ($locked->cod_collected) {
                throw ValidationException::withMessages(['shipment' => 'COD cash was already collected for this delivery.']);
            }

            $amounts = RiderDeliveries::amounts($locked);
            $now = now();
            $locked->update([
                'cod_collected' => true,
                'cod_collected_at' => $now,
                'cod_collected_by' => $rider->id,
            ]);

            // Preserve an immutable per-shipment record in the existing payment payload; values come from the order,
            // never from the rider's request.
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->first();
            if ($payment) {
                $payload = $payment->payload ?? [];
                $payload['cod_collections'][(string) $locked->id] = [
                    'collected_amount' => $amounts['cash_to_collect'],
                    'rider_commission' => $amounts['rider_commission'],
                    'amount_due_logistics' => $amounts['amount_to_hand_logistics'],
                    'collected_by' => $rider->id,
                    'collected_at' => $now->toIso8601String(),
                ];
                $payment->update(['payload' => $payload]);
            }

            $this->wallet->credit($locked, $rider);
            if ($order->buyer) {
                $this->history->collection($locked, $rider, $amounts, $order->buyer);
            }
            if (RiderDeliveries::tracksDecisions()) {
                RiderDeliveryLog::create([
                    'shipment_id' => $locked->id,
                    'user_id' => $rider->id,
                    'action' => RiderDeliveryLog::COD_COLLECTED,
                    'note' => 'Collected ₱'.number_format($amounts['cash_to_collect'], 2).'; ₱'.number_format($amounts['rider_commission'], 2).' retained for delivery.',
                ]);
            }

            return $locked->fresh();
        });
    }
}
