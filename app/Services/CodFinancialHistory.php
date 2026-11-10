<?php

namespace App\Services;

use App\Models\CodFinancialTransaction;
use App\Models\LogisticsCodSettlement;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Writes append-only COD financial events from the trusted delivery and settlement workflows. */
class CodFinancialHistory
{
    public static function ready(): bool
    {
        return Schema::hasTable('cod_financial_transactions');
    }

    public function collection(Shipment $shipment, User $rider, array $amounts, User $buyer): void
    {
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        $base = ['order_id' => $order?->id, 'shipment_id' => $shipment->id, 'logistics_provider_id' => $shipment->logistics_provider_id, 'recorded_by' => $rider->id, 'occurred_at' => now()];
        $this->record(CodFinancialTransaction::BUYER_COLLECTION, $base + ['amount' => $amounts['cash_to_collect'], 'from_user_id' => $buyer->id, 'to_user_id' => $rider->id, 'status' => 'completed']);
        $this->record(CodFinancialTransaction::RIDER_COMMISSION, $base + ['amount' => $amounts['rider_commission'], 'to_user_id' => $rider->id, 'status' => 'completed']);
    }

    public function allocation(LogisticsCodSettlement $settlement, User $actor): void
    {
        $base = ['order_id' => $settlement->order_id, 'shipment_id' => $settlement->shipment_id, 'logistics_provider_id' => $settlement->logistics_provider_id, 'recorded_by' => $actor->id, 'occurred_at' => now()];
        $providerUser = $settlement->provider?->user_id;
        $this->record(CodFinancialTransaction::RIDER_TO_LOGISTICS, $base + ['amount' => $settlement->amount_received, 'from_user_id' => $settlement->handover?->rider_user_id, 'to_user_id' => $providerUser, 'status' => 'completed']);
        $this->record(CodFinancialTransaction::LOGISTICS_COMMISSION, $base + ['amount' => $settlement->logistics_earnings, 'to_user_id' => $providerUser, 'status' => 'completed']);
        $this->record(CodFinancialTransaction::ADMIN_COMMISSION, $base + ['amount' => $settlement->admin_earnings, 'status' => 'allocated', 'metadata' => ['shipping' => $settlement->admin_shipping_earnings, 'product' => $settlement->admin_product_commission]]);
        $this->record(CodFinancialTransaction::SELLER_PAYABLE, $base + ['amount' => $settlement->seller_earnings, 'from_user_id' => $providerUser, 'to_user_id' => $settlement->seller_user_id, 'status' => 'pending']);
    }

    public function sellerSettlement(LogisticsCodSettlement $settlement, User $actor): void
    {
        $this->record(CodFinancialTransaction::SELLER_SETTLEMENT, [
            'order_id' => $settlement->order_id, 'shipment_id' => $settlement->shipment_id, 'logistics_provider_id' => $settlement->logistics_provider_id,
            'amount' => $settlement->seller_earnings, 'from_user_id' => $settlement->provider?->user_id, 'to_user_id' => $settlement->seller_user_id,
            'recorded_by' => $actor->id, 'occurred_at' => $settlement->settled_at ?? now(), 'status' => 'completed', 'metadata' => ['reference' => $settlement->seller_reference],
        ]);
    }

    /** @return Collection<int, CodFinancialTransaction> */
    public function forShipment(Shipment $shipment, User $viewer): Collection
    {
        abort_unless($this->mayView($shipment, $viewer), 403);

        return self::ready() ? CodFinancialTransaction::where('shipment_id', $shipment->id)->with(['fromUser', 'toUser', 'recorder'])->orderBy('occurred_at')->get() : collect();
    }

    private function record(string $type, array $attributes): void
    {
        if (! self::ready()) {
            return;
        }
        CodFinancialTransaction::firstOrCreate(['shipment_id' => $attributes['shipment_id'], 'type' => $type], $attributes + ['type' => $type]);
    }

    private function mayView(Shipment $shipment, User $viewer): bool
    {
        if ($viewer->hasRole('admin')) {
            return true;
        }
        if ($viewer->hasRole('rider')) {
            return $shipment->riderUserId() === $viewer->id;
        }
        if ($viewer->hasRole('logistics')) {
            return (int) $shipment->logistics_provider_id === (int) $viewer->logisticsProvider?->id;
        }
        if ($viewer->hasRole('seller')) {
            return ShipmentWorkflow::sellerUserId($shipment) === $viewer->id;
        }
        $order = $shipment->order ?: $shipment->sellerOrder?->order;

        return $viewer->hasRole('buyer') && (int) $order?->buyer?->id === (int) $viewer->id;
    }
}
