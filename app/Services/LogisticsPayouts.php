<?php

namespace App\Services;

use App\Models\LogisticsPayout;
use App\Models\LogisticsProvider;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New orders allocate shipping and 10% of product value to Logistics, including its rider obligation.
 * Admin pays prepaid allocations in batches. COD allocations are already handled by the hub.
 * Historical orders retain their original recorded distribution.
 * (LogisticsPayout); a partner with several branches is paid as one company and pays its branches itself.
 */
class LogisticsPayouts
{
    public const PRODUCT_COMMISSION_RATE = Settlement::PRODUCT_COMMISSION_BPS / 10000;

    /** Whether the database can track payouts (the 2026_11_04 migration ran and parcels record their partner). */
    public static function ready(): bool
    {
        return Shipment::tracksProvider() && Schema::hasTable('logistics_payouts') && Schema::hasColumn('shipments', 'logistics_payout_id');
    }

    public static function partnerPercent(): int
    {
        return (int) round(self::PRODUCT_COMMISSION_RATE * 100);
    }

    /**
     * Every delivered parcel with a partner, newest first.
     *
     * @return Collection<int, array{shipment_id:int, tracking:string, provider_id:int, provider:string, delivered_at:?Carbon,
     *     fee:float, partner:float, rider:float, zaylo:float, payout_id:?int}>
     */
    public function rows(?LogisticsProvider $provider = null): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return $this->delivered($provider)->with([...$this->relations(), 'provider'])->get()
            ->map(fn (Shipment $shipment) => $this->row($shipment))
            ->sortByDesc(fn (array $row) => $row['delivered_at']?->getTimestamp() ?? 0)->values();
    }

    /** Totals over rows: shipping fees, each side's share, rider commissions, and what is paid and still owed to partners. */
    public function summary(Collection $rows): array
    {
        $unpaid = $rows->where('requires_payout', true)->whereNull('payout_id');

        return [
            'parcels' => $rows->count(),
            'fees' => round($rows->sum('fee'), 2),
            'partner' => round($rows->sum('partner'), 2),
            'rider' => round($rows->sum('rider'), 2),
            'company' => round($rows->sum('company'), 2),
            'zaylo' => round($rows->sum('zaylo'), 2),
            'paid' => round($rows->whereNotNull('payout_id')->sum('partner'), 2),
            'owed' => round($unpaid->sum('partner'), 2),
            'unpaidParcels' => $unpaid->count(),
        ];
    }

    /** The summary per partner, most owed first. */
    public function byProvider(Collection $rows): Collection
    {
        return $rows->groupBy('provider_id')
            ->map(fn (Collection $group) => ['provider_id' => $group->first()['provider_id'], 'provider' => $group->first()['provider']] + $this->summary($group))
            ->sortByDesc('owed')->values();
    }

    /**
     * Pays the partner its share of every delivered parcel not paid yet. Returns null when nothing is owed.
     */
    public function pay(LogisticsProvider $provider, User $admin, ?string $reference = null): ?LogisticsPayout
    {
        if (! self::ready()) {
            return null;
        }

        return DB::transaction(function () use ($provider, $admin, $reference) {
            // Locked so two admins paying at once cannot pay the same parcels twice.
            $shipments = $this->delivered($provider)->whereNull('logistics_payout_id')->lockForUpdate()->with($this->relations())->get();
            if ($shipments->isEmpty()) {
                return null;
            }
            $rows = $shipments->map(fn (Shipment $shipment) => $this->row($shipment))->where('requires_payout', true);
            if ($rows->isEmpty()) {
                return null;
            }
            if (blank($reference)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reference' => 'Enter the actual partner transfer reference.']);
            }

            $payout = $provider->payouts()->create([
                'parcels' => $rows->count(),
                'shipping_fees' => round($rows->sum('fee'), 2),
                'amount' => round($rows->sum('partner'), 2),
                'partner_share' => self::PRODUCT_COMMISSION_RATE,
            ] + (Schema::hasColumn('logistics_payouts', 'rider_commissions') ? [
                'rider_commissions' => round($rows->sum('rider'), 2),
            ] : []) + [
                'reference' => filled($reference) ? trim($reference) : null,
                'paid_by' => $admin->id,
                'paid_at' => now(),
            ]);
            Shipment::whereKey($rows->pluck('shipment_id'))->update(['logistics_payout_id' => $payout->id]);
            app(ActivityLogger::class)->record('admin.logistics-payouts.pay', 'settlement', 'Recorded partner payout.', $admin, $payout, ['amount' => $payout->amount, 'parcels' => $payout->parcels]);

            return $payout;
        });
    }

    /** The full delivery charge, including seller- or Admin-funded free shipping. */
    public static function feeOf(Shipment $shipment): float
    {
        $snapshot = $shipment->sellerOrder?->settlement_breakdown;
        if ($snapshot) {
            return (float) $snapshot['shipping'];
        }
        if ((int) $shipment->getAttribute('fee_minor') > 0) {
            return round($shipment->fee_minor / 100, 2);
        }
        if ($shipment->sellerOrder) {
            return round((int) $shipment->sellerOrder->shipping_fee_minor / 100, 2);
        }
        $order = $shipment->order;

        return round((float) ($order?->shipping_fee ?? 0) + (float) ($order?->shipping_discount ?? 0), 2);
    }

    private function row(Shipment $shipment): array
    {
        $fee = self::feeOf($shipment);
        $split = app(Settlement::class)->forShipment($shipment);
        // A rider is credited only when the authenticated delivery workflow
        // completes. This keeps historical/manual shipment rows from gaining a
        // wallet credit without an assigned rider.
        $rider = RiderWallet::ready() ? round((float) ($shipment->riderCommission?->amount ?? 0), 2) : 0.0;
        $newPolicy = ($split['policy_version'] ?? 1) >= 2;
        $partner = $split['logistics_gross_earnings'] ?? $split['logistics_earnings'];
        $order = $shipment->order ?: $shipment->sellerOrder?->order;

        return [
            'shipment_id' => $shipment->id,
            'tracking' => $shipment->tracking_number ?? $shipment->tracking_code ?? '#'.$shipment->id,
            'provider_id' => (int) $shipment->logistics_provider_id,
            'provider' => $shipment->provider?->name ?? 'Logistics partner #'.$shipment->logistics_provider_id,
            'delivered_at' => RiderDeliveries::deliveredAt($shipment),
            'fee' => $fee,
            'partner' => $partner,
            'company' => round($partner - $rider, 2),
            'rider' => $rider,
            'zaylo' => $split['admin_earnings'],
            'payout_id' => $shipment->logistics_payout_id ? (int) $shipment->logistics_payout_id : null,
            // COD money is handed to Logistics, not held by Admin. Never pay that allocation again.
            'requires_payout' => ! ($newPolicy && $order?->payment_method === 'cod'),
        ];
    }

    private function delivered(?LogisticsProvider $provider): Builder
    {
        return ShipmentWorkflow::whereStage(Shipment::query(), ['delivered'])
            ->whereNotNull('logistics_provider_id')
            ->when($provider, fn (Builder $query) => $query->where('logistics_provider_id', $provider->id));
    }

    /** The order the fee comes from, on whichever schema this database uses. */
    private function relations(): array
    {
        return array_values(array_filter([
            Schema::hasColumn('shipments', 'order_id') ? 'order' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder' : null,
            RiderWallet::ready() ? 'riderCommission' : null,
        ]));
    }
}
