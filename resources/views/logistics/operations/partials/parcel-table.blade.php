{{-- Parcels with their current step and what can happen next.
     Expects $shipments; optional $riders (show the assign form), $hideActions, $empty (title, text). --}}
@php
    $riders = $riders ?? null;
    $couriers = $couriers ?? null;
    $hideActions = $hideActions ?? false;
@endphp

<div class="seller-table-wrap">
    <table class="seller-table workspace-table parcel-table">
        <thead>
            <tr>
                <th>Parcel</th>
                <th>Seller</th>
                <th>Deliver to</th>
                <th>Step</th>
                <th>Personnel</th>
                @unless($hideActions)<th><span class="sr-only">Actions</span></th>@endunless
            </tr>
        </thead>
        <tbody>
            @forelse($shipments as $shipment)
                @php $parcel = \App\Support\ParcelView::for($shipment); @endphp
                <tr>
                    <td>
                        <strong class="parcel-tracking">{{ $parcel['tracking'] }}</strong>
                        <span class="seller-cell-note">Order {{ $parcel['order'] }}</span>
                        @foreach(($shipment->sellerOrder?->items ?? $shipment->order?->items ?? collect()) as $item)
                            <span class="seller-cell-note">{{ $item->product_name }} &times; {{ $item->quantity }}</span>
                        @endforeach
                        @include('partials.waybill-queue', ['queue' => \App\Support\WaybillQueue::for($shipment), 'note' => 'Print for seller'])
                    </td>
                    <td>
                        <strong>{{ $parcel['seller'] }}</strong>
                        @if($parcel['pickup_address'] && $parcel['pickup_address'] !== '—')
                            <span class="seller-cell-note"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $parcel['pickup_address'] }}</span>
                        @endif
                        @if($parcel['seller_phone'])
                            <span class="seller-cell-note"><i class="fas fa-phone" aria-hidden="true"></i> {{ $parcel['seller_phone'] }}</span>
                        @endif
                    </td>
                    <td class="parcel-destination">
                        <strong>{{ $parcel['recipient'] }}</strong>
                        <span class="seller-cell-note">{{ $parcel['destination'] }}</span>
                    </td>
                    <td>
                        <span class="parcel-stage parcel-stage--{{ $parcel['stage'] }}">{{ $parcel['label'] }}</span>
                        @if($parcel['stage'] === 'confirmed' && $parcel['pickup_on'])
                            <span class="seller-cell-note parcel-note">Pickup {{ $parcel['pickup_on']->isToday() ? 'today' : $parcel['pickup_on']->format('D, M j') }}</span>
                        @elseif($parcel['area'])
                            <span class="seller-cell-note parcel-note"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $parcel['area'] }}</span>
                        @endif
                        <span class="seller-cell-note">{{ $parcel['updated']?->timezone('Asia/Manila')->format('M j, g:i A') }}</span>
                        {{-- Proof of delivery: the rider's photo, taken when the parcel was handed over. --}}
                        @if($shipment->delivery_proof_path)
                            @include('logistics.operations.partials.proof-photo', [
                                'url' => route('delivery-proof', $shipment),
                                'label' => 'Delivery photo',
                                'alt' => 'Rider\'s delivery photo for parcel '.$parcel['tracking'],
                            ])
                        @endif
                    </td>
                    <td>
                        @if($parcel['pickup_courier'])
                            <span class="seller-cell-note"><strong>Pickup:</strong> {{ $parcel['pickup_courier'] }}</span>
                        @endif
                        @if($shipment->pickup_scanned_at)
                            <span class="seller-cell-note">Scanned {{ $shipment->pickup_scanned_at->timezone('Asia/Manila')->format('M j, g:i A') }}</span>
                        @endif
                        @if($shipment->hub_received_at)
                            <span class="seller-cell-note">Handover confirmed by {{ $shipment->hubReceiver?->name }} on {{ $shipment->hub_received_at->timezone('Asia/Manila')->format('M j, g:i A') }}</span>
                        @endif
                        @if($parcel['rider'])
                            <span class="seller-cell-note"><strong>Delivery:</strong> {{ $parcel['rider'] }}</span>
                        @elseif(! $parcel['pickup_courier'])
                            —
                        @endif
                    </td>
                    @unless($hideActions)
                        <td>@include('logistics.operations.partials.parcel-actions', ['shipment' => $shipment, 'riders' => $riders, 'couriers' => $couriers])</td>
                    @endunless
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hideActions ? 5 : 6 }}">
                        <div class="seller-empty">
                            <i class="fas {{ $empty['icon'] ?? 'fa-box-open' }}" aria-hidden="true"></i>
                            <strong>{{ $empty['title'] ?? 'No parcels here' }}</strong>
                            <span>{{ $empty['text'] ?? 'Parcels appear here when they reach this step.' }}</span>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($shipments, 'hasPages') && $shipments->hasPages())
    <div class="seller-pagination">{{ $shipments->links() }}</div>
@endif
