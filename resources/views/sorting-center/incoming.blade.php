@extends('sorting-center.layout')

@section('page-title', 'Incoming parcels')

@section('module-content')
    <div class="sc-page-heading"><div><div class="sc-eyebrow">Inbound operations</div><h1>Incoming parcels</h1><p>Review shipments that are ready to enter the sorting workflow.</p></div><a class="sc-button sc-button-secondary" href="{{ route('sorting-center.sorting') }}"><i class="fa-solid fa-barcode" aria-hidden="true"></i> Open scanner</a></div>
    <section class="sc-card">
        <div class="sc-card-header"><div><h2>Ready for intake</h2><p>Live parcel records with ready or unassigned status.</p></div><span class="sc-badge sc-badge-info">{{ $shipments->total() }} parcels</span></div>
        <div class="sc-card-body"><form class="sc-toolbar" method="GET" action="{{ route('sorting-center.incoming') }}">
            <label class="sc-field">Search tracking code<input class="sc-input" type="search" name="search" value="{{ $search }}" placeholder="Enter a tracking number"></label>
            <button class="sc-button" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
            @if ($search)<a class="sc-button sc-button-secondary" href="{{ route('sorting-center.incoming') }}">Clear</a>@endif
        </form></div>
        <div class="sc-table-wrap"><table class="sc-table">
            <thead><tr><th>Tracking ID</th><th>Order</th><th>Seller / provider</th><th>Destination</th><th>Received</th><th>Status</th><th>Next step</th></tr></thead>
            <tbody>
                @forelse ($shipments as $shipment)
                    @php($order = $shipment->order ?: $shipment->sellerOrder?->order)
                    <tr><td><strong>{{ $shipment->tracking_code ?: $shipment->tracking_number ?: '—' }}</strong></td><td>{{ $order?->order_number ?: 'Order #'.$shipment->id }}</td><td>{{ $shipment->provider?->name ?: '—' }}</td><td>{{ data_get($order?->shipping_address, 'city') ?: $order?->recipient_name ?: '—' }}</td><td>{{ $shipment->created_at?->format('M j, Y · g:i A') }}</td><td><span class="sc-badge sc-badge-warning">{{ str_replace('_', ' ', $shipment->status) }}</span></td><td><a class="sc-button sc-button-secondary sc-button-small" href="{{ route('sorting-center.sorting', ['tracking' => $shipment->tracking_code ?: $shipment->tracking_number]) }}">View / sort</a></td></tr>
                @empty
                    <tr><td class="sc-empty" colspan="7"><i class="fa-solid fa-box-open" aria-hidden="true"></i>No incoming parcels match this search.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="sc-pagination">{{ $shipments->links() }}</div>
    </section>
@endsection
