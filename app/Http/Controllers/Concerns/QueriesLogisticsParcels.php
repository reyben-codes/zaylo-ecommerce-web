<?php

namespace App\Http\Controllers\Concerns;

use App\Models\LogisticsProvider;
use App\Models\Shipment;
use App\Services\ShipmentWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Parcels handled by the signed-in logistics partner: the ones sellers sent to this partner, plus open pickup requests
 * that name no partner (sent when none was available), which the first partner to confirm takes.
 */
trait QueriesLogisticsParcels
{
    protected function logisticsProvider(): ?LogisticsProvider
    {
        return auth()->user()?->logisticsProvider;
    }

    protected function parcels(): Builder
    {
        $provider = $this->logisticsProvider();

        return Shipment::query()->when(
            $provider && Shipment::tracksProvider(),
            fn (Builder $query) => $query->where(fn (Builder $visible) => $visible
                ->where('logistics_provider_id', $provider->id)
                ->orWhere(fn (Builder $open) => ShipmentWorkflow::whereStage($open->whereNull('logistics_provider_id'), ['ready']))),
        );
    }

    protected function withParcelDetails(Builder $query): Builder
    {
        return $query->with($this->detailRelations());
    }

    /** Relations the parcel lists show, on whichever schema this database uses. */
    protected function detailRelations(): array
    {
        return array_values(array_filter([
            Schema::hasColumn('shipments', 'order_id') ? 'order.seller' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder.order' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder.seller' : null,
            Schema::hasColumn('shipments', 'rider_id') ? 'rider.user' : null,
            Schema::hasColumn('shipments', 'courier_id') ? 'courier' : null,
        ]));
    }

    /** @return array<string,int> stage => number of parcels */
    protected function parcelStageCounts(): array
    {
        return collect(ShipmentWorkflow::STAGES)
            ->map(fn ($label, $stage) => ShipmentWorkflow::whereStage($this->parcels(), [$stage])->count())
            ->all();
    }

    /** Another partner's parcel is off limits; an open pickup request may be confirmed or declined by any partner. */
    protected function ownsParcel(Shipment $shipment, bool $orOpenRequest = false): void
    {
        $provider = $this->logisticsProvider();
        if (! $provider || ! Shipment::tracksProvider() || (int) $shipment->logistics_provider_id === (int) $provider->id) {
            return;
        }
        abort_unless($orOpenRequest && $shipment->logistics_provider_id === null && ShipmentWorkflow::stageOf($shipment) === 'ready', 403);
    }
}
