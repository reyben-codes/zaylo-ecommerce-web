@extends('layouts.app')

@section('title', 'Sorting Center · ZAYLO')

@section('nav-links')
    <nav class="nav-links">
        <a class="active-link" href="{{ route('sorting-center.dashboard') }}">Sorting center</a>
    </nav>
@endsection

@push('styles')
    <style>
        .sorting-center-page { padding: 28px 0 64px; }
        .sorting-center-header { display: flex; justify-content: space-between; gap: 24px; align-items: end; margin-bottom: 28px; }
        .sorting-center-header h1 { margin: 0 0 8px; }
        .sorting-center-header p { margin: 0; color: #74685f; }
        .sorting-center-filter { display: flex; gap: 10px; align-items: center; }
        .sorting-center-filter select, .sorting-center-filter button { min-height: 42px; border: 1px solid #d8d0c8; background: #fff; padding: 0 14px; font: inherit; }
        .sorting-center-filter button { background: #1e1e1e; color: #fff; cursor: pointer; }
        .sorting-center-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 24px; }
        .sorting-center-metric { border-top: 3px solid #c8a27a; background: #f7f3ef; padding: 18px; }
        .sorting-center-metric span { display: block; color: #74685f; font-size: .76rem; letter-spacing: .08em; text-transform: uppercase; }
        .sorting-center-metric strong { display: block; margin-top: 8px; font-size: 1.7rem; }
        .sorting-center-table-wrap { overflow-x: auto; background: #fff; border: 1px solid #e5ded7; }
        .sorting-center-table { width: 100%; min-width: 760px; border-collapse: collapse; }
        .sorting-center-table th, .sorting-center-table td { padding: 15px 16px; border-bottom: 1px solid #eee8e3; text-align: left; vertical-align: middle; }
        .sorting-center-table th { color: #74685f; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; }
        .sorting-center-table td { font-size: .9rem; }
        .sorting-center-table small { color: #74685f; display: block; margin-top: 4px; }
        .sorting-center-status { display: inline-block; padding: 5px 9px; background: #f1ebe5; color: #5d4b3d; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; }
        .sorting-center-action { border: 1px solid #d8d0c8; background: #fff; padding: 8px 10px; cursor: pointer; font: inherit; }
        .sorting-center-empty { padding: 32px; text-align: center; color: #74685f; }
        @media (max-width: 760px) { .sorting-center-header { align-items: start; flex-direction: column; } .sorting-center-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>
@endpush

@section('content')
    <main class="sorting-center-page">
        <div class="sorting-center-header">
            <div>
                <h1>Sorting center</h1>
                <p>Monitor parcels and move each shipment through the fulfillment line.</p>
            </div>
            <form class="sorting-center-filter" method="GET" action="{{ route('sorting-center.dashboard') }}">
                <label class="sr-only" for="shipment-status">Filter by status</label>
                <select id="shipment-status" name="status">
                    <option value="">All shipments</option>
                    @foreach ($counts as $key => $count)
                        <option value="{{ $key }}" @selected($status === $key)>{{ str_replace('_', ' ', ucfirst($key)) }} ({{ $count }})</option>
                    @endforeach
                </select>
                <button type="submit">Filter</button>
            </form>
        </div>

        <div class="sorting-center-metrics">
            @foreach (['unassigned' => 'Awaiting sort', 'assigned' => 'Assigned', 'in_transit' => 'In transit', 'delivered' => 'Delivered'] as $key => $label)
                <div class="sorting-center-metric"><span>{{ $label }}</span><strong>{{ $counts[$key] }}</strong></div>
            @endforeach
        </div>

        <div class="sorting-center-table-wrap">
            <table class="sorting-center-table">
                <thead><tr><th>Tracking</th><th>Order</th><th>Destination</th><th>Status</th><th>Update</th></tr></thead>
                <tbody>
                    @forelse ($shipments as $shipment)
                        @php($order = $shipment->order ?: $shipment->sellerOrder?->order)
                        <tr>
                            <td><strong>{{ $shipment->tracking_code ?: $shipment->tracking_number ?: '—' }}</strong><small>{{ $shipment->provider?->name ?: 'No provider' }}</small></td>
                            <td>{{ $order?->order_number ?: 'Order #' . $shipment->id }}</td>
                            <td>{{ data_get($order?->shipping_address, 'city') ?: $order?->recipient_name ?: '—' }}</td>
                            <td><span class="sorting-center-status">{{ str_replace('_', ' ', $shipment->status) }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('sorting-center.shipments.status', $shipment) }}">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="status-{{ $shipment->id }}">New status</label>
                                    <select id="status-{{ $shipment->id }}" name="status" onchange="this.form.submit()" class="sorting-center-action">
                                        @foreach ($counts as $key => $count)<option value="{{ $key }}" @selected($shipment->status === $key)>{{ str_replace('_', ' ', ucfirst($key)) }}</option>@endforeach
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="sorting-center-empty">No shipments match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:20px;">{{ $shipments->links() }}</div>
    </main>
@endsection