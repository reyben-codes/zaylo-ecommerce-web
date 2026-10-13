@extends('sorting-center.layout')

@section('page-title', 'Delivery assignment')

@section('module-content')
    <div class="sc-page-heading"><div><div class="sc-eyebrow">Dispatch planning</div><h1>Delivery assignment</h1><p>Allocate unassigned parcels to an active rider for delivery.</p></div></div>
    <section class="sc-card">
        <div class="sc-card-header"><div><h2>Awaiting a rider</h2><p>Assignments write to the existing shipment rider and status fields.</p></div><span class="sc-badge sc-badge-warning">{{ $shipments->total() }} awaiting</span></div>
        <div class="sc-card-body">
            <form class="sc-toolbar" method="GET" action="{{ route('sorting-center.assignments') }}">
                <label class="sc-field">Filter by delivery area<input class="sc-input" type="search" name="area" value="{{ $area }}" placeholder="City or province"></label>
                <button class="sc-button" type="submit"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Find area</button>
                @if ($area)<a class="sc-button sc-button-secondary" href="{{ route('sorting-center.assignments') }}">Clear</a>@endif
            </form>
        </div>
        <div class="sc-table-wrap"><table class="sc-table">
            <thead><tr><th>Parcel</th><th>Order</th><th>Destination / area</th><th>Status</th><th>Assign rider</th></tr></thead>
            <tbody>
                @forelse ($shipments as $shipment)
                    @php($order = $shipment->order ?: $shipment->sellerOrder?->order)
                    <tr>
                        <td><strong>{{ $shipment->tracking_code ?: $shipment->tracking_number ?: 'Shipment #'.$shipment->id }}</strong><small>Received {{ $shipment->created_at?->format('M j, Y') }}</small></td>
                        <td>{{ $order?->order_number ?: 'Order #'.$shipment->id }}</td>
                        <td>{{ data_get($order?->shipping_address, 'city') ?: $order?->recipient_name ?: '—' }}</td>
                        <td><span class="sc-badge sc-badge-info">{{ str_replace('_', ' ', $shipment->status) }}</span></td>
                        <td>
                            @if ($riders->isNotEmpty())
                                <form method="POST" action="{{ route('sorting-center.assignments.store', $shipment) }}">
                                    @csrf
                                    <label class="sr-only" for="rider-{{ $shipment->id }}">Select rider for parcel {{ $shipment->tracking_code ?: $shipment->id }}</label>
                                    <select class="sc-select" id="rider-{{ $shipment->id }}" name="rider_id" required>
                                        <option value="">Choose rider</option>
                                        @foreach ($riders as $rider)
                                            <option value="{{ $rider->id }}">{{ $rider->user?->name ?: 'Rider #'.$rider->id }}</option>
                                        @endforeach
                                    </select>
                                    <button class="sc-button sc-button-small" type="submit">Assign</button>
                                </form>
                            @else
                                <span class="sc-badge sc-badge-warning">No active riders</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="sc-empty"><i class="fa-solid fa-check-double" aria-hidden="true"></i>All eligible parcels are assigned.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="sc-pagination">{{ $shipments->links() }}</div>
    </section>
@endsection
