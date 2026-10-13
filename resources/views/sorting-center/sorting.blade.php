@extends('sorting-center.layout')

@section('page-title', 'Parcel sorting')

@section('module-content')
    <div class="sc-page-heading"><div><div class="sc-eyebrow">Scan & route</div><h1>Parcel sorting</h1><p>Look up a live shipment by its tracking code and update its operational status.</p></div></div>
    <section class="sc-card">
        <div class="sc-card-header"><div><h2>Scan parcel</h2><p>Use a barcode scanner that types into the field, or enter the tracking code manually.</p></div><span class="sc-badge sc-badge-info"><i class="fa-solid fa-database" aria-hidden="true"></i> Live shipment lookup</span></div>
        <div class="sc-card-body">
            <form class="sc-toolbar" method="GET" action="{{ route('sorting-center.sorting') }}" data-sc-scan-form>
                <label class="sc-field">Tracking code
                    <input class="sc-input" type="search" name="tracking" value="{{ $tracking }}" placeholder="Scan or enter parcel tracking code" autocomplete="off" autofocus required>
                </label>
                <button class="sc-button" type="submit"><i class="fa-solid fa-barcode" aria-hidden="true"></i> Find parcel</button>
            </form>
            @if ($tracking !== '')
                @if ($shipment)
                    @php($order = $shipment->order ?: $shipment->sellerOrder?->order)
                    <div class="sc-demo-notice"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Shipment found in the existing records. Saving a status below updates the shipment using the existing authenticated Sorting Center workflow.</span></div>
                    <div class="sc-sort-result">
                        <div class="sc-detail"><span>Tracking ID</span><strong>{{ $shipment->tracking_code ?: $shipment->tracking_number ?: '—' }}</strong></div>
                        <div class="sc-detail"><span>Order reference</span><strong>{{ $order?->order_number ?: 'Order #'.$shipment->id }}</strong></div>
                        <div class="sc-detail"><span>Destination</span><strong>{{ data_get($order?->shipping_address, 'city') ?: $order?->recipient_name ?: '—' }}</strong></div>
                        <div class="sc-detail"><span>Sorting status</span><strong><span @class(['sc-badge', 'sc-badge-success' => $shipment->status === 'delivered', 'sc-badge-warning' => in_array($shipment->status, ['unassigned', 'ready'], true), 'sc-badge-info' => in_array($shipment->status, ['assigned', 'picked_up', 'in_transit', 'out_for_delivery'], true), 'sc-badge-danger' => in_array($shipment->status, ['failed', 'returned', 'cancelled'], true)])>{{ str_replace('_', ' ', $shipment->status) }}</span></strong></div>
                        <div class="sc-detail"><span>Rider / provider</span><strong>{{ $shipment->rider?->user?->name ?: $shipment->provider?->name ?: 'Not assigned' }}</strong></div>
                        <div class="sc-detail"><span>Last updated</span><strong>{{ $shipment->updated_at?->format('M j, Y · g:i A') ?: '—' }}</strong></div>
                    </div>
                    <form class="sc-toolbar" style="margin-top:20px" method="POST" action="{{ route('sorting-center.shipments.status', $shipment) }}">
                        @csrf @method('PATCH')
                        <label class="sc-field">Update parcel status
                            <select class="sc-select" name="status">
                                @foreach (['unassigned', 'assigned', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'] as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($shipment->status === $statusOption)>{{ str_replace('_', ' ', ucfirst($statusOption)) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="sc-button" type="submit">Save status</button>
                    </form>
                @else
                    <div class="sc-alert sc-alert-error" role="status">No shipment with tracking code “{{ $tracking }}” was found. Try checking the label and scanning again.</div>
                @endif
            @else
                <div class="sc-card" style="border-style:dashed;background:#fafbfd">
                    <div class="sc-card-body" style="text-align:center;padding:38px 20px;color:#728096">
                        <i class="fa-solid fa-barcode" aria-hidden="true" style="display:block;margin-bottom:12px;color:#98a8ba;font-size:2rem"></i>
                        Scan or enter a tracking ID to view parcel details and its current status.
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
