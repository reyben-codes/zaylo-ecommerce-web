<?php

namespace App\Http\Controllers\Concerns;

use App\Models\LogisticsProvider;
use App\Models\Shipment;
use App\Services\ShipmentWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Parcels handled by the signed-in logistics partner. Where the schema records the partner on a shipment the list is
 * limited to that partner; the deployed schema has one shared queue.
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
            $provider && Schema::hasColumn('shipments', 'logistics_provider_id'),
            fn (Builder $query) => $query->where('logistics_provider_id', $provider->id),
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

    protected function ownsParcel(Shipment $shipment): void
    {
        $provider = $this->logisticsProvider();
        abort_if($provider && Schema::hasColumn('shipments', 'logistics_provider_id') && (int) $shipment->logistics_provider_id !== (int) $provider->id, 403);
    }
}
