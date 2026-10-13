@extends('sorting-center.layout')

@section('page-title', 'Delivery monitoring')

@section('module-content')
    <div class="sc-page-heading"><div><div class="sc-eyebrow">Shipment visibility</div><h1>Delivery monitoring</h1><p>Search live parcels and follow their latest recorded delivery status.</p></div></div>
    <section class="sc-card">
        <div class="sc-card-header"><div><h2>Shipment progress</h2><p>Status reflects the existing shipment record.</p></div><span class="sc-badge sc-badge-info">{{ $shipments->total() }} results</span></div>
        <div class="sc-card-body"><form class="sc-toolbar" method="GET" action="{{ route('sorting-center.monitoring') }}">
            <label class="sc-field">Search tracking code<input class="sc-input" type="search" name="search" value="{{ $search }}" placeholder="Tracking number"></label>
            <label class="sc-field">Status
                <select class="sc-select" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="sc-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filter</button>
            @if ($search || $status)<a class="sc-button sc-button-secondary" href="{{ route('sorting-center.monitoring') }}">Reset</a>@endif
        </form></div>
        <div class="sc-table-wrap"><table class="sc-table">
            <thead><tr><th>Tracking</th><th>Destination</th><th>Rider</th><th>Provider</th><th>Current status</th><th>Last update</th></tr></thead>
            <tbody>
                @forelse ($shipments as $shipment)
                    @php($order = $shipment->order ?: $shipment->sellerOrder?->order)
                    <tr>
                        <td><strong>{{ $shipment->tracking_code ?: $shipment->tracking_number ?: '—' }}</strong><small>{{ $order?->order_number ?: 'Order #'.$shipment->id }}</small></td>
                        <td>{{ data_get($order?->shipping_address, 'city') ?: $order?->recipient_name ?: '—' }}</td>
                        <td>{{ $shipment->rider?->user?->name ?: '—' }}</td><td>{{ $shipment->provider?->name ?: '—' }}</td>
                        <td><span @class(['sc-badge', 'sc-badge-success' => $shipment->status === 'delivered', 'sc-badge-warning' => in_array($shipment->status, ['unassigned', 'ready'], true), 'sc-badge-info' => in_array($shipment->status, ['assigned', 'picked_up', 'in_transit', 'out_for_delivery'], true), 'sc-badge-danger' => in_array($shipment->status, ['failed', 'returned', 'cancelled'], true)])>{{ str_replace('_', ' ', $shipment->status) }}</span></td>
                        <td>{{ $shipment->updated_at?->diffForHumans() ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="sc-empty"><i class="fa-solid fa-route" aria-hidden="true"></i>No delivery records match these filters.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="sc-pagination">{{ $shipments->links() }}</div>
    </section>
@endsection
