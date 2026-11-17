@extends('layouts.workspace', ['workspace' => 'logistics'])
@section('title', 'Hub transfers')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush
@section('content')
<section class="seller-page-heading"><div><p class="seller-eyebrow">{{ $hub->name }}</p><h1>Hub-to-hub transfers</h1><p>Receive seller parcels here, send them to an approved hub in the buyer's city within your network, then sort by barangay at the destination hub.</p></div>@if(\App\Services\LogisticsAccess::canRoute(auth()->user(), 'logistics.sorting'))<a href="{{ route('logistics.sorting') }}" class="seller-button seller-button--secondary">Barangay sorting</a>@endif</section>
@if($errors->any())<div class="seller-panel" role="alert">@foreach($errors->all() as $error)<p class="seller-field__error">{{ $error }}</p>@endforeach</div>@endif
<section class="seller-panel"><header class="seller-panel__header"><div><h2>Ready for hub dispatch</h2><p>Scan the parcel waybill and select its destination hub. Only hubs in the correct city and logistics network are offered.</p></div></header>
@forelse($toSend as $shipment)
    @php $parcel = \App\Support\ParcelView::for($shipment); $destinations = $routing->destinations($shipment); @endphp
    <article class="parcel-transfer-card">
        <h3>{{ $parcel['tracking'] }}</h3><p>{{ $parcel['recipient'] }} · {{ $parcel['destination'] }}</p>
        @if(! \App\Services\HubRouting::location($shipment))
            <p class="seller-cell-note">This older parcel has no saved city and barangay snapshot. Its existing local workflow remains available; do not guess a hub destination from the address text.</p>
        @elseif(! \App\Services\HubRouting::needsTransfer($shipment))
            <p class="seller-cell-note">This hub serves the delivery city. Continue to barangay sorting for local delivery.</p>
        @elseif($destinations->isEmpty())
            <p class="seller-field__error">No approved hub in your network matches the buyer's city. Ask the admin to approve a hub with the correct saved city before dispatching.</p>
        @else
            <details data-smooth-disclosure>
                <summary class="seller-button seller-button--small">Dispatch to destination hub</summary>
                <div data-disclosure-content><form method="POST" action="{{ route('logistics.hub-transfers.dispatch', $shipment) }}" class="parcel-transfer-form" data-confirm="Confirm this parcel is leaving your hub for the selected destination hub?" data-confirm-button="Dispatch parcel">
                    @csrf
                    <label for="hub-{{ $shipment->id }}">Destination hub</label><select id="hub-{{ $shipment->id }}" name="destination_hub_id" required><option value="">Choose destination hub</option>@foreach($destinations as $destination)<option value="{{ $destination->id }}">{{ $destination->name }} · {{ $destination->city_name }}, {{ $destination->province_name }}</option>@endforeach</select>
                    <label for="dispatch-tracking-{{ $shipment->id }}">Scan or type tracking number</label><input id="dispatch-tracking-{{ $shipment->id }}" name="tracking" maxlength="100" autocomplete="off" required>
                    <button type="submit" class="seller-button">Confirm dispatch</button>
                </form></div>
            </details>
        @endif
    </article>
@empty<p class="parcel-empty-inline">No parcels are waiting for hub dispatch.</p>@endforelse
{{ $toSend->withQueryString()->links() }}
</section>
<section class="seller-panel"><header class="seller-panel__header"><div><h2>Incoming from other hubs</h2><p>Confirm physical receipt using the parcel's waybill before it enters your sorting queue.</p></div></header>
@forelse($incoming as $shipment)
    @php $parcel = \App\Support\ParcelView::for($shipment); @endphp
    <article class="parcel-transfer-card"><h3>{{ $parcel['tracking'] }} · from {{ $shipment->originHub?->name }}</h3><p>{{ $parcel['recipient'] }} · {{ $parcel['destination'] }}</p>
        @if(\App\Services\LogisticsAccess::canRoute(auth()->user(), 'logistics.hub-transfers.receive'))
        <details data-smooth-disclosure><summary class="seller-button seller-button--small">Receive transfer</summary><div data-disclosure-content>
            <form method="POST" action="{{ route('logistics.hub-transfers.receive', $shipment) }}" class="parcel-transfer-form">
                @csrf
                <label for="receive-tracking-{{ $shipment->id }}">Scan or type tracking number</label><input id="receive-tracking-{{ $shipment->id }}" name="tracking" maxlength="100" autocomplete="off" required>
                <label><input type="checkbox" name="parcel_received" value="1" required> Parcel physically received at this hub</label>
                <label><input type="checkbox" name="tracking_verified" value="1" required> Waybill and destination verified</label>
                <button class="seller-button" type="submit">Confirm receipt and send to sorting</button>
            </form>
        </div></details>
        @else<p class="seller-cell-note">Sorting staff or a hub supervisor must confirm physical receipt.</p>@endif
    </article>
@empty<p class="parcel-empty-inline">No hub transfers are awaiting receipt.</p>@endforelse
{{ $incoming->withQueryString()->links() }}
</section>
<section class="seller-panel"><header class="seller-panel__header"><h2>Recent hub routes</h2></header><div class="seller-table-wrap"><table class="seller-table"><thead><tr><th>Parcel</th><th>Origin</th><th>Destination</th><th>Progress</th></tr></thead><tbody>
@forelse($history as $shipment)<tr><td>{{ $shipment->tracking_number }}</td><td>{{ $shipment->originHub?->name }}</td><td>{{ $shipment->destinationHub?->name }}</td><td>{{ \App\Services\ShipmentWorkflow::labelOf($shipment) }}@if($shipment->destination_received_at)<span class="seller-cell-note">Received {{ $shipment->destination_received_at->timezone('Asia/Manila')->format('M j, g:i A') }}</span>@endif</td></tr>@empty<tr><td colspan="4">No hub transfers yet.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
