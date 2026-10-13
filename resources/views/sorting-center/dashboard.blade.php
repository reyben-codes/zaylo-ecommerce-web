@extends('sorting-center.layout')

@section('page-title', 'Operations overview')

@section('module-content')
    <div class="sc-page-heading">
        <div>
            <div class="sc-eyebrow">Control tower</div>
            <h1>Good work starts with clear visibility.</h1>
            <p>Track parcels moving through your sorting center and keep deliveries on schedule.</p>
        </div>
        <a class="sc-button" href="{{ route('sorting-center.sorting') }}"><i class="fa-solid fa-barcode" aria-hidden="true"></i> Scan a parcel</a>
    </div>

    <section class="sc-metrics" aria-label="Shipment summary">
        @foreach ([
            ['label' => 'Awaiting sort', 'value' => $counts['unassigned'], 'icon' => 'fa-boxes-stacked', 'note' => 'Parcels ready for processing'],
            ['label' => 'Assigned', 'value' => $counts['assigned'], 'icon' => 'fa-truck-fast', 'note' => 'Allocated to a delivery rider'],
            ['label' => 'In transit', 'value' => $counts['in_transit'], 'icon' => 'fa-route', 'note' => 'Currently on the move'],
            ['label' => 'Delivered', 'value' => $counts['delivered'], 'icon' => 'fa-circle-check', 'note' => 'Completed shipments'],
        ] as $metric)
            <article class="sc-card sc-metric">
                <span class="sc-metric-icon"><i class="fa-solid {{ $metric['icon'] }}" aria-hidden="true"></i></span>
                <span class="sc-metric-label">{{ $metric['label'] }}</span>
                <strong class="sc-metric-value">{{ number_format($metric['value']) }}</strong>
                <div class="sc-metric-note">{{ $metric['note'] }}</div>
            </article>
        @endforeach
    </section>

    <section class="sc-card">
        <div class="sc-card-header">
            <div><h2>Shipment activity</h2><p>Live shipment records · update a parcel status from the final column.</p></div>
            <a class="sc-button sc-button-secondary sc-button-small" href="{{ route('sorting-center.monitoring') }}">View monitoring</a>
        </div>
        <div class="sc-card-body">
            <form class="sc-toolbar" method="GET" action="{{ route('sorting-center.dashboard') }}">
                <label class="sc-field">Search parcel or order
                    <input class="sc-input" type="search" name="search" value="{{ $search }}" placeholder="Tracking code or order number">
                </label>
                <label class="sc-field">Shipment status
                    <select class="sc-select" name="status">
                        <option value="">All shipments</option>
                        @foreach ($counts as $key => $count)
                            <option value="{{ $key }}" @selected($status === $key)>{{ str_replace('_', ' ', ucfirst($key)) }} ({{ $count }})</option>
                        @endforeach
                    </select>
                </label>
                <button class="sc-button" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Apply filters</button>
                @if ($status || $search)
                    <a class="sc-button sc-button-secondary" href="{{ route('sorting-center.dashboard') }}">Clear</a>
                @endif
            </form>
        </div>
        <div class="sc-table-wrap">
            <table class="sc-table">
                <thead><tr><th>Tracking ID</th><th>Order</th><th>Destination</th><th>Rider / provider</th><th>Status</th><th>Update status</th></tr></thead>
                <tbody>
                    @forelse ($shipments as $shipment)
                        @php($order = $shipment->order ?: $shipment->sellerOrder?->order)
                        <tr>
                            <td><strong>{{ $shipment->tracking_code ?: $shipment->tracking_number ?: '—' }}</strong></td>
                            <td>{{ $order?->order_number ?: 'Order #'.$shipment->id }}</td>
                            <td>{{ data_get($order?->shipping_address, 'city') ?: $order?->recipient_name ?: '—' }}</td>
                            <td>{{ $shipment->rider?->user?->name ?: $shipment->provider?->name ?: 'Unassigned' }}</td>
                            <td><span @class(['sc-badge', 'sc-badge-success' => $shipment->status === 'delivered', 'sc-badge-warning' => in_array($shipment->status, ['unassigned', 'ready'], true), 'sc-badge-info' => in_array($shipment->status, ['assigned', 'picked_up', 'in_transit', 'out_for_delivery'], true), 'sc-badge-danger' => in_array($shipment->status, ['failed', 'returned', 'cancelled'], true)])>{{ str_replace('_', ' ', $shipment->status) }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('sorting-center.shipments.status', $shipment) }}">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="status-{{ $shipment->id }}">New status for {{ $shipment->tracking_code ?: $shipment->tracking_number }}</label>
                                    <select class="sc-select" id="status-{{ $shipment->id }}" name="status">
                                        @foreach ($counts as $key => $count)
                                            <option value="{{ $key }}" @selected($shipment->status === $key)>{{ str_replace('_', ' ', ucfirst($key)) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="sc-button sc-button-small" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="sc-empty" colspan="6"><i class="fa-regular fa-folder-open" aria-hidden="true"></i>No shipments match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sc-pagination">{{ $shipments->links() }}</div>
    </section>
@endsection
