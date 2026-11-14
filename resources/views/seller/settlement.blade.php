@extends('layouts.seller')

@section('title', 'Sales proceeds')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Sales proceeds</h1>
            <p>Your verified cash-on-delivery proceeds for {{ $range['label'] }}. Admin and Logistics each receive 10% of product value. Your own free-shipping vouchers are deducted from your remaining share. A transfer is paid only after Logistics records its reference.</p>
        </div>
    </section>

    @if(! $ready)
        <aside class="seller-notice"><i class="fas fa-database" aria-hidden="true"></i><div><strong>COD settlement tracking is being set up.</strong><p>Your earnings appear after migrations run and Logistics verifies the rider handover.</p></div></aside>
    @endif

    <section class="seller-panel admin-filter-panel">
        <form method="GET" action="{{ route('seller.settlement') }}" class="admin-filter-form">
            <div class="seller-field"><label class="seller-field__label" for="from">From</label><input id="from" name="from" type="date" value="{{ $range['from'] }}"></div>
            <div class="seller-field"><label class="seller-field__label" for="to">To</label><input id="to" name="to" type="date" value="{{ $range['to'] }}"></div>
            <div class="seller-field"><label class="seller-field__label" for="status">Settlement status</label><select id="status" name="status"><option value="">All settlements</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="admin-form-footer"><button type="submit" class="seller-button"><i class="fas fa-filter" aria-hidden="true"></i> Apply filters</button><a href="{{ route('seller.settlement') }}" class="seller-button seller-button--secondary">Reset</a></div>
        </form>
    </section>

    <section class="seller-stat-grid" aria-label="COD earnings summary">
        <article class="seller-stat"><span class="seller-stat__label">Product sales</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['product_sales'], 2) }}</strong><span class="seller-stat__note">Verified COD product value</span></article>
        <article class="seller-stat"><span class="seller-stat__label">Net proceeds</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['cod_earnings'], 2) }}</strong><span class="seller-stat__note">After commissions and your shipping vouchers</span></article>
        <article class="seller-stat"><span class="seller-stat__label">Pending settlement</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['pending_earnings'], 2) }}</strong><span class="seller-stat__note">{{ number_format($summary['pending_count']) }} awaiting transfer</span></article>
        <article class="seller-stat"><span class="seller-stat__label">Paid settlements</span><strong class="seller-stat__value">&#8369;{{ number_format($summary['completed_earnings'], 2) }}</strong><span class="seller-stat__note">{{ number_format($summary['completed_count']) }} transfers recorded</span></article>
    </section>

    <aside class="seller-notice"><i class="fas fa-circle-info" aria-hidden="true"></i><div><strong>How your proceeds are calculated</strong><p>Product value minus 10% Admin commission, 10% Logistics commission, and shipping covered by your own vouchers. Buyers pay shipping when no shipping voucher is used; Admin covers its global free-shipping vouchers. These are sales proceeds before your product and business costs, not a salary or profit. A pending entry is not paid until its transfer reference is recorded.</p></div></aside>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Order-level earnings</h2><p>Only your own COD orders are shown.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Order / parcel</th><th>Product value</th><th>Admin 10%</th><th>Logistics 10%</th><th>Your shipping voucher</th><th>Net proceeds</th><th>Settlement status</th></tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        @php $order = $row->shipment?->order ?: $row->shipment?->sellerOrder?->order; @endphp
                        <tr>
                            <td><strong>{{ $order?->order_number ?? $order?->reference ?? 'Order #'.$row->order_id }}</strong><span class="seller-cell-note">{{ $row->shipment?->tracking_number ?? $row->shipment?->tracking_code ?? 'Parcel #'.$row->shipment_id }} · {{ $row->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</span></td>
                            <td>&#8369;{{ number_format($row->product_price, 2) }}</td>
                            <td>&#8369;{{ number_format($row->admin_product_commission, 2) }}</td>
                            <td>&#8369;{{ number_format($row->settlement_breakdown['logistics_product_commission'] ?? round($row->product_price - $row->admin_product_commission - $row->seller_earnings, 2), 2) }}</td>
                            <td>&#8369;{{ number_format($row->settlement_breakdown['free_shipping_by_seller'] ?? 0, 2) }}</td>
                            <td><strong>&#8369;{{ number_format($row->seller_earnings, 2) }}</strong></td>
                            <td>
                                @if($row->status === \App\Models\LogisticsCodSettlement::SETTLED)
                                    <span class="commission-state commission-state--settled">Paid</span><span class="seller-cell-note">{{ $row->settled_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} · Ref {{ $row->seller_reference }}</span>
                                @else
                                    <span class="commission-state commission-state--pending">Pending settlement</span><span class="seller-cell-note">Awaiting Logistics transfer</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="seller-empty"><i class="fas fa-wallet" aria-hidden="true"></i><strong>No verified COD earnings yet</strong><span>COD earnings appear after a rider handover is verified by Logistics.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>GCash sales proceeds</h2><p>Completed GCash orders placed during this date range. Buyers paid you directly; reserve the fees below for remittance. These rows do not record a fee transfer.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Order</th><th>Product value</th><th>Admin commission</th><th>Logistics commission</th><th>Your shipping voucher</th><th>Net proceeds</th><th>Fees to remit</th></tr></thead>
                <tbody>
                    @forelse($prepaid as $sale)
                        <tr>
                            <td><strong>{{ $sale['number'] }}</strong></td>
                            <td>&#8369;{{ number_format($sale['product_price'], 2) }}</td>
                            <td>&#8369;{{ number_format($sale['admin_product_commission'], 2) }}</td>
                            <td>&#8369;{{ number_format($sale['logistics_product_commission'], 2) }}</td>
                            <td>&#8369;{{ number_format($sale['free_shipping_by_seller'] ?? 0, 2) }}</td>
                            <td><strong>&#8369;{{ number_format($sale['seller_earnings'], 2) }}</strong></td>
                            <td>&#8369;{{ number_format($sale['seller_owes_zaylo'], 2) }}<span class="seller-cell-note">From the payment you received</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="admin-empty-inline">No completed GCash sales in this date range.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
