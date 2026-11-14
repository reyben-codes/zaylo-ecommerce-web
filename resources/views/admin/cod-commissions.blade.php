@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'COD commissions')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Finance</p>
            <h1>COD commission management</h1>
            <p>Verified cash-on-delivery settlements for {{ $range['label'] }}. For new orders, Admin and Logistics each receive 10% of product value. Logistics receives shipping and pays the rider separately. Sellers fund their own free-shipping vouchers; Admin funds its global shipping vouchers. Earlier settlements keep their recorded allocations.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('admin.commissions') }}" class="seller-button seller-button--secondary"><i class="fas fa-percent" aria-hidden="true"></i> Platform commission</a>
        </div>
    </section>

    @if(! $ready)
        <aside class="seller-notice">
            <i class="fas fa-database" aria-hidden="true"></i>
            <div><strong>COD settlement records are being set up.</strong><p>Run the latest database migrations before COD allocations can appear here.</p></div>
        </aside>
    @endif

    <section class="seller-panel admin-filter-panel">
        <form method="GET" action="{{ route('admin.cod-commissions') }}" class="admin-filter-form">
            <div class="seller-field"><label class="seller-field__label" for="from">From</label><input id="from" name="from" type="date" value="{{ $range['from'] }}"></div>
            <div class="seller-field"><label class="seller-field__label" for="to">To</label><input id="to" name="to" type="date" value="{{ $range['to'] }}"></div>
            <div class="seller-field"><label class="seller-field__label" for="order">Order</label><input id="order" name="order" value="{{ $filters['order'] ?? '' }}" placeholder="Order number"></div>
            <div class="seller-field"><label class="seller-field__label" for="seller">Seller</label><select id="seller" name="seller"><option value="">All sellers</option>@foreach($sellers as $id => $name)<option value="{{ $id }}" @selected((int) ($filters['seller'] ?? 0) === (int) $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="seller-field"><label class="seller-field__label" for="rider">Rider</label><select id="rider" name="rider"><option value="">All riders</option>@foreach($riders as $id => $name)<option value="{{ $id }}" @selected((int) ($filters['rider'] ?? 0) === (int) $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="seller-field"><label class="seller-field__label" for="logistics">Logistics</label><select id="logistics" name="logistics"><option value="">All partners</option>@foreach($providers as $id => $name)<option value="{{ $id }}" @selected((int) ($filters['logistics'] ?? 0) === (int) $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="seller-field"><label class="seller-field__label" for="status">Settlement</label><select id="status" name="status"><option value="">All states</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="admin-form-footer"><button type="submit" class="seller-button"><i class="fas fa-filter" aria-hidden="true"></i> Apply filters</button><a href="{{ route('admin.cod-commissions') }}" class="seller-button seller-button--secondary">Reset</a></div>
        </form>
    </section>

    <section class="seller-stat-grid" aria-label="COD settlement summary">
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-receipt" aria-hidden="true"></i> COD transactions</span><strong class="seller-stat__value">{{ number_format($summary['transactions']) }}</strong><span class="seller-stat__note">Verified rider handovers</span></div>
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> COD collections</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['collections'], 2) }}</strong><span class="seller-stat__note">Verified cash received from riders</span></div>
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-truck" aria-hidden="true"></i> Logistics earnings</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['logistics_earnings'], 2) }}</strong><span class="seller-stat__note">After the recorded rider allocation</span></div>
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-motorcycle" aria-hidden="true"></i> Rider earnings</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['rider_earnings'], 2) }}</strong><span class="seller-stat__note">Agreed rate saved with each order</span></div>
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-truck-ramp-box" aria-hidden="true"></i> Admin shipping adjustment</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['admin_shipping'], 2) }}</strong><span class="seller-stat__note">Historical shipping income less Admin-funded shipping</span></div>
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-percent" aria-hidden="true"></i> Admin product</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['admin_product'], 2) }}</strong><span class="seller-stat__note">10% of product value</span></div>
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-building-columns" aria-hidden="true"></i> Admin earnings</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['admin_earnings'], 2) }}</strong><span class="seller-stat__note">Product commission after shipping subsidies</span></div>
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-store" aria-hidden="true"></i> Seller earnings</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['seller_earnings'], 2) }}</strong><span class="seller-stat__note">After commissions and seller-funded shipping</span></div>
    </section>

    <section class="seller-stat-grid" aria-label="COD settlement status">
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Pending settlements</span><strong class="seller-stat__value">{{ number_format($summary['pending_count']) }}</strong><span class="seller-stat__note">₱{{ number_format($summary['pending_amount'], 2) }} seller transfers pending</span></div>
        <div class="seller-stat"><span class="seller-stat__label"><i class="fas fa-circle-check" aria-hidden="true"></i> Completed settlements</span><strong class="seller-stat__value">{{ number_format($summary['settled_count']) }}</strong><span class="seller-stat__note">₱{{ number_format($summary['settled_amount'], 2) }} transferred to sellers</span></div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Order-level COD breakdown</h2><p>Every amount is from the verified settlement record; a parcel remains pending until Logistics records its seller-transfer reference.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Order / parcel</th><th>Seller</th><th>Rider / Logistics</th><th>Collected</th><th>Rider</th><th>Logistics</th><th>Admin shipping adjustment</th><th>Admin product</th><th>Admin total</th><th>Seller</th><th>Settlement</th></tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        @php $order = $row->shipment?->order ?: $row->shipment?->sellerOrder?->order; @endphp
                        <tr>
                            <td><strong>{{ $order?->order_number ?? $order?->reference ?? 'Order #'.$row->order_id }}</strong><span class="seller-cell-note">{{ $row->shipment?->tracking_number ?? $row->shipment?->tracking_code ?? 'Parcel #'.$row->shipment_id }} · {{ $row->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</span></td>
                            <td>{{ $row->seller?->name ?? '—' }}</td>
                            <td>{{ $row->handover?->rider?->name ?? '—' }}<span class="seller-cell-note">{{ $row->provider?->name ?? '—' }}</span></td>
                            <td>&#8369;{{ number_format($row->amount_received, 2) }}</td>
                            <td>&#8369;{{ number_format($row->rider_earnings, 2) }}</td>
                            <td>&#8369;{{ number_format($row->logistics_earnings, 2) }}</td>
                            <td>&#8369;{{ number_format($row->admin_shipping_earnings, 2) }}</td>
                            <td>&#8369;{{ number_format($row->admin_product_commission, 2) }}</td>
                            <td><strong>&#8369;{{ number_format($row->admin_earnings, 2) }}</strong>@include('partials.cod-admin-transfer', ['settlement' => $row])</td>
                            <td>&#8369;{{ number_format($row->seller_earnings, 2) }}</td>
                            <td><span class="commission-state commission-state--{{ $row->status === \App\Models\LogisticsCodSettlement::SETTLED ? 'settled' : 'pending' }}">{{ $statuses[$row->status] ?? $row->status }}</span>@if($row->seller_reference)<span class="seller-cell-note">{{ $row->seller_reference }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><p class="admin-empty-inline">No COD settlements match these filters.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
