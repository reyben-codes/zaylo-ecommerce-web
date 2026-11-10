<?php

namespace App\Services;

use App\Models\LogisticsCodSettlement;
use App\Models\LogisticsProvider;
use App\Models\RiderCashHandover;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Allocates verified COD handovers and records the seller transfer that completes each parcel's settlement. */
class LogisticsCodSettlements
{
    public function __construct(private readonly CodFinancialHistory $history) {}

    public static function ready(): bool
    {
        return Schema::hasTable('logistics_cod_settlements');
    }

    /** @param Collection<int, array{shipment: Shipment, cash: float}> $held */
    public function allocate(RiderCashHandover $handover, Collection $held, User $actor): void
    {
        if (! self::ready() || (float) $handover->shortfall > 0) {
            return;
        }

        foreach ($held as $row) {
            $shipment = Shipment::query()->whereKey($row['shipment']->id)->lockForUpdate()->firstOrFail();
            $shipment->loadMissing(['order', 'sellerOrder.order', 'sellerOrder.seller']);
            $order = $shipment->order ?: $shipment->sellerOrder?->order;
            $split = app(Settlement::class)->forShipment($shipment);
            $received = RiderDeliveries::amounts($shipment)['amount_to_hand_logistics'];
            $distributed = round($split['logistics_earnings'] + $split['admin_earnings'] + $split['seller_earnings'], 2);
            if (round($received, 2) !== $distributed) {
                throw ValidationException::withMessages(['received' => 'This parcel cannot be settled because its COD allocation does not balance.']);
            }

            $settlement = LogisticsCodSettlement::firstOrCreate(['shipment_id' => $shipment->id], [
                'rider_cash_handover_id' => $handover->id,
                'logistics_provider_id' => $handover->logistics_provider_id,
                'order_id' => $order?->id,
                'seller_user_id' => ShipmentWorkflow::sellerUserId($shipment),
                'product_price' => $split['product_price'],
                'amount_received' => $received,
                'rider_earnings' => $split['rider_earnings'],
                'logistics_earnings' => $split['logistics_earnings'],
                'admin_shipping_earnings' => $split['admin_shipping_earnings'],
                'admin_product_commission' => $split['admin_product_commission'],
                'admin_earnings' => $split['admin_earnings'],
                'seller_earnings' => $split['seller_earnings'],
                'status' => LogisticsCodSettlement::AWAITING_SELLER_TRANSFER,
                'recorded_by' => $actor->id,
            ]);
            // The unique shipment constraint makes a retried handover harmless; the same immutable event set is kept.
            $this->history->allocation($settlement->load(['provider', 'handover']), $actor);
        }
    }

    /** @return Collection<int, LogisticsCodSettlement> */
    public function forProvider(LogisticsProvider $provider, int $limit = 100): Collection
    {
        return self::ready()
            ? LogisticsCodSettlement::with(['shipment', 'seller', 'handover.rider', 'recorder', 'settler'])
                ->where('logistics_provider_id', $provider->id)->latest('id')->limit($limit)->get()
            : collect();
    }

    /** @param array{order?: ?string, seller?: ?int, rider?: ?int, logistics?: ?int, status?: ?string} $filters */
    public function adminRows(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        $rows = LogisticsCodSettlement::with([
            'shipment.order', 'shipment.sellerOrder.order', 'seller', 'provider', 'handover.rider', 'recorder', 'settler',
        ])->whereBetween('created_at', [$from, $to])
            ->when($filters['seller'] ?? null, fn ($query, $id) => $query->where('seller_user_id', $id))
            ->when($filters['logistics'] ?? null, fn ($query, $id) => $query->where('logistics_provider_id', $id))
            ->when($filters['rider'] ?? null, fn ($query, $id) => $query->whereHas('handover', fn ($handover) => $handover->where('rider_user_id', $id)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->get();

        $order = trim((string) ($filters['order'] ?? ''));
        if ($order === '') {
            return $rows;
        }
        $needle = mb_strtolower($order);

        return $rows->filter(function (LogisticsCodSettlement $row) use ($needle) {
            $order = $row->shipment?->order ?: $row->shipment?->sellerOrder?->order;
            $number = (string) ($order?->order_number ?? $order?->reference ?? $row->order_id);

            return str_contains(mb_strtolower($number), $needle);
        })->values();
    }

    /** @return array<string, float|int> */
    public function adminSummary(Collection $rows): array
    {
        $pending = $rows->where('status', LogisticsCodSettlement::AWAITING_SELLER_TRANSFER);
        $settled = $rows->where('status', LogisticsCodSettlement::SETTLED);

        return [
            'transactions' => $rows->count(),
            'collections' => round($rows->sum('amount_received'), 2),
            'admin_shipping' => round($rows->sum('admin_shipping_earnings'), 2),
            'admin_product' => round($rows->sum('admin_product_commission'), 2),
            'admin_earnings' => round($rows->sum('admin_earnings'), 2),
            'logistics_earnings' => round($rows->sum('logistics_earnings'), 2),
            'rider_earnings' => round($rows->sum('rider_earnings'), 2),
            'seller_earnings' => round($rows->sum('seller_earnings'), 2),
            'pending_count' => $pending->count(),
            'pending_amount' => round($pending->sum('seller_earnings'), 2),
            'settled_count' => $settled->count(),
            'settled_amount' => round($settled->sum('seller_earnings'), 2),
        ];
    }

    /** @return Collection<int, LogisticsCodSettlement> Only records belonging to this seller account. */
    public function forSeller(User $seller, Carbon $from, Carbon $to, ?string $status = null): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return LogisticsCodSettlement::with(['shipment.order', 'shipment.sellerOrder.order', 'provider', 'settler'])
            ->where('seller_user_id', $seller->id)->whereBetween('created_at', [$from, $to])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')->get();
    }

    /** @return array<string, float|int> */
    public function sellerSummary(Collection $rows): array
    {
        $pending = $rows->where('status', LogisticsCodSettlement::AWAITING_SELLER_TRANSFER);
        $settled = $rows->where('status', LogisticsCodSettlement::SETTLED);

        return [
            'product_sales' => round($rows->sum('product_price'), 2),
            'cod_earnings' => round($rows->sum('seller_earnings'), 2),
            'pending_count' => $pending->count(),
            'pending_earnings' => round($pending->sum('seller_earnings'), 2),
            'completed_count' => $settled->count(),
            'completed_earnings' => round($settled->sum('seller_earnings'), 2),
        ];
    }

    /** Records the actual seller transfer. The allocated Admin share remains auditable on the same settlement row. */
    public function settle(LogisticsCodSettlement $settlement, LogisticsProvider $provider, User $actor, string $reference): LogisticsCodSettlement
    {
        abort_unless((int) $settlement->logistics_provider_id === (int) $provider->id, 403);
        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages(['seller_reference' => 'Enter the seller transfer reference.']);
        }

        return DB::transaction(function () use ($settlement, $provider, $actor, $reference) {
            $locked = LogisticsCodSettlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->logistics_provider_id === (int) $provider->id, 403);
            if ($locked->status === LogisticsCodSettlement::SETTLED) {
                throw ValidationException::withMessages(['settlement' => 'This COD parcel was already settled.']);
            }
            if ($locked->status !== LogisticsCodSettlement::AWAITING_SELLER_TRANSFER) {
                throw ValidationException::withMessages(['settlement' => 'This COD parcel is not ready for seller settlement.']);
            }

            $locked->update([
                'status' => LogisticsCodSettlement::SETTLED,
                'seller_reference' => mb_substr($reference, 0, 100),
                'settled_by' => $actor->id,
                'settled_at' => now(),
            ]);
            $this->history->sellerSettlement($locked->load('provider'), $actor);
            app(ActivityLogger::class)->record(
                'logistics.cod_settled', 'settlement', 'COD seller transfer recorded for parcel #'.$locked->shipment_id.'.',
                $actor, $locked, ['seller_earnings' => $locked->seller_earnings, 'admin_earnings' => $locked->admin_earnings, 'reference' => $locked->seller_reference],
            );

            return $locked->fresh();
        });
    }
}
