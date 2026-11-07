<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\RiderCashHandover;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Cash on delivery, from the buyer's door to the hub. Every COD parcel a rider delivered is cash the rider holds until
 * the hub counts it (usually at time-out). The partner records what it received; the difference is a shortfall kept
 * against the rider. GCash orders were paid to the seller, so riders collect nothing for them.
 */
class CashHandovers
{
    public function __construct(private readonly RiderDeliveries $deliveries) {}

    public static function ready(): bool
    {
        return Schema::hasTable('rider_cash_handovers') && Schema::hasColumn('shipments', 'cash_handover_id');
    }

    /** Delivered COD parcels whose cash the rider has not handed over yet, with the amount for each. */
    public function held(User $rider): Collection
    {
        if (! self::ready()) {
            return collect();
        }
        $relations = array_values(array_filter([
            Schema::hasColumn('shipments', 'order_id') ? 'order' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder.order' : null,
        ]));

        return $this->deliveries->completed($rider)->whereNull('cash_handover_id')->with($relations)->get()
            ->map(fn (Shipment $shipment) => ['shipment' => $shipment, 'cash' => RiderDeliveries::amounts($shipment)['cash_to_collect']])
            ->filter(fn (array $row) => $row['cash'] > 0)->values();
    }

    /** @return Collection<int, array{rider: Rider, parcels: int, cash: float}> the partner's riders holding COD cash */
    public function owedTo(LogisticsProvider $provider): Collection
    {
        return Rider::with('user')->where('logistics_provider_id', $provider->id)->where('status', 'approved')->get()
            ->map(function (Rider $rider) {
                $held = $rider->user ? $this->held($rider->user) : collect();

                return ['rider' => $rider, 'parcels' => $held->count(), 'cash' => round($held->sum('cash'), 2)];
            })
            ->filter(fn (array $row) => $row['parcels'] > 0)->sortByDesc('cash')->values();
    }

    /** The hub counted the rider's cash: every parcel held is settled, and any shortfall is recorded against the rider. */
    public function record(LogisticsProvider $provider, User $rider, float $received, ?string $note, User $recorder): RiderCashHandover
    {
        abort_unless((int) $rider->rider?->logistics_provider_id === (int) $provider->id, 403);

        return DB::transaction(function () use ($provider, $rider, $received, $note, $recorder) {
            $held = $this->held($rider);
            if ($held->isEmpty()) {
                throw ValidationException::withMessages(['received' => $rider->name.' has no cash on delivery to hand over.']);
            }
            $expected = round($held->sum('cash'), 2);
            $shortfall = round(max(0, $expected - $received), 2);
            if ($shortfall > 0 && blank($note)) {
                throw ValidationException::withMessages(['note' => 'The cash is ₱'.number_format($shortfall, 2).' short. Note what the rider said.']);
            }
            $handover = RiderCashHandover::create([
                'logistics_provider_id' => $provider->id, 'rider_user_id' => $rider->id, 'parcels' => $held->count(),
                'expected' => $expected, 'received' => round($received, 2), 'shortfall' => $shortfall,
                'note' => $note, 'recorded_by' => $recorder->id,
            ]);
            Shipment::whereKey($held->pluck('shipment.id'))->update(['cash_handover_id' => $handover->id]);

            return $handover;
        });
    }

    public function history(LogisticsProvider $provider, int $limit = 30): Collection
    {
        return self::ready()
            ? RiderCashHandover::with(['rider', 'recorder'])->where('logistics_provider_id', $provider->id)->latest('id')->limit($limit)->get()
            : collect();
    }
}
