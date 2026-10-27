@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    @php
        $maxSales = max(1, (float) $series->max('sales'));
        $percent = rtrim(rtrim(number_format($rate * 100, 2), '0'), '.');
        $scopeTitle = $seller ? $seller['name'] : 'Marketplace';
        if ($product) {
            $scopeTitle .= ' · '.$product['name'];
        }
    @endphp
    @include('partials.print-header', ['title' => $scopeTitle.' reports · '.$range['label']])
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Finance</p>
            <h1>{{ $scopeTitle.' · reports' }}</h1>
            <p>{{ $seller ? 'Sales and commission for '.$seller['name'] : 'Marketplace sales and platform commission across all sellers' }}{{ $product ? ' for "'.$product['name'].'"' : '' }} for orders placed {{ $range['label'] }} (Philippine time).</p>
        </div>
        <div class="seller-page-heading__actions">
            @if($product)
                <a href="{{ route('admin.reports', array_filter(['from' => $range['from'], 'to' => $range['to'], 'seller' => $seller['id'] ?? null])) }}" class="seller-button seller-button--secondary"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> All items</a>
            @endif
            @if($seller)
                <a href="{{ route('admin.reports', array_filter(['from' => $range['from'], 'to' => $range['to'], 'product' => $product['id'] ?? null])) }}" class="seller-button seller-button--secondary"><i class="fas fa-users" aria-hidden="true"></i> All sellers</a>
            @endif
            {{-- Reports are printed or saved only from "Download report" below, after choosing the options. --}}
        </div>
    </section>

    <section class="seller-panel admin-filter-panel">
        @include('admin.partials.date-range', ['action' => route('admin.reports'), 'range' => $range, 'sellers' => $sellers, 'seller' => $seller, 'products' => $products, 'product' => $product])
    </section>

    <section class="seller-panel admin-download-panel">
        <header class="seller-panel__header"><div><h2>Download report</h2><p>Choose the report, the seller, specific item, and dates, then download it as a PDF.</p></div></header>
        <form method="GET" action="{{ route('admin.reports.download') }}" class="seller-filter-bar admin-range">
            <div class="seller-field">
                <label class="seller-field__label" for="download-report">Report</label>
                <select id="download-report" name="report">
                    <option value="sales">Sales summary</option>
                    <option value="commission">Commission report</option>
                </select>
            </div>
            <div class="seller-field">
                <label class="seller-field__label" for="download-seller">Seller</label>
                <select id="download-seller" name="seller">
                    <option value="">All sellers</option>
                    @foreach($sellers as $id => $name)
                        <option value="{{ $id }}" @selected(($seller['id'] ?? null) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="seller-field">
                <label class="seller-field__label" for="download-product">Item</label>
                <select id="download-product" name="product">
                    <option value="">All items</option>
                    @foreach($products as $id => $name)
                        <option value="{{ $id }}" @selected(($product['id'] ?? null) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="seller-field">
                <label class="seller-field__label" for="download-from">From</label>
                <input type="date" id="download-from" name="from" value="{{ $range['from'] }}" max="{{ now('Asia/Manila')->toDateString() }}">
            </div>
            <div class="seller-field">
                <label class="seller-field__label" for="download-to">To</label>
                <input type="date" id="download-to" name="to" value="{{ $range['to'] }}" max="{{ now('Asia/Manila')->toDateString() }}">
            </div>
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-file-arrow-down" aria-hidden="true"></i> Download PDF</button>
            @error('from')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
            @error('report')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
        </form>
    </section>

    @include('admin.partials.money-summary')

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Sales over time</h2><p>Merchandise sales per {{ $monthly ? 'month' : 'day' }}.</p></div></header>
        @if($series->sum('sales') > 0)
            <div class="admin-chart" role="img" aria-label="Sales per period, highest &#8369;{{ number_format($maxSales, 2) }}">
                @foreach($series as $point)
                    <div class="admin-chart__bar" title="{{ $point['label'] }}: &#8369;{{ number_format($point['sales'], 2) }} · {{ $point['orders'] }} {{ Str::plural('order', $point['orders']) }}">
                        <span style="--height: {{ max(2, round($point['sales'] / $maxSales * 100)) }}%"></span>
                        @php $step = (int) ceil($loop->count / 8); @endphp
                        {{-- About eight labels, always the first and last, never crowding the last one. --}}
                        @if($loop->count <= 14 || $loop->first || $loop->last || ($loop->index % $step === 0 && $loop->remaining >= $step / 2))
                            <em>{{ $point['label'] }}</em>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="admin-chart__scale">Highest: &#8369;{{ number_format($maxSales, 2) }}</p>
        @else
            <p class="admin-empty-inline">No sales in this period.</p>
        @endif
    </section>

    <div class="seller-panel-grid admin-two-col">
        <section class="seller-panel">
            <header class="seller-panel__header"><div><h2>Top sellers</h2><p>By sales in this period.</p></div></header>
            <div class="seller-table-wrap">
                <table class="seller-table workspace-table admin-money">
                    <thead><tr><th>Seller</th><th>Gross</th><th>Net</th><th>Commission</th><th>Orders</th></tr></thead>
                    <tbody>
                        @forelse($bySeller->take(10) as $shop)
                            <tr><td>{{ $shop['seller'] }}</td><td>&#8369;{{ number_format($shop['sales'], 2) }}</td><td>&#8369;{{ number_format($shop['net'], 2) }}</td><td>&#8369;{{ number_format($shop['commission'], 2) }}</td><td>{{ $shop['orders'] }}</td></tr>
                        @empty
                            <tr><td colspan="5"><p class="admin-empty-inline">No sales.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="seller-panel">
            <header class="seller-panel__header"><div><h2>Best-selling products</h2><p>By quantity sold.</p></div></header>
            <div class="seller-table-wrap">
                <table class="seller-table workspace-table admin-money">
                    <thead><tr><th>Product</th><th>Sold</th><th>Sales</th></tr></thead>
                    <tbody>
                        @forelse($topProducts as $product)
                            <tr><td>{{ $product['name'] }}</td><td>{{ $product['quantity'] }}</td><td>&#8369;{{ number_format($product['amount'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="3"><p class="admin-empty-inline">No products sold.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Orders by status</h2></div></header>
        <ul class="admin-status-list">
            @forelse($byStatus as $status => $count)
                <li><span>{{ Str::headline($status) }}</span><strong>{{ $count }}</strong></li>
            @empty
                <li><span>No orders</span><strong>0</strong></li>
            @endforelse
        </ul>
    </section>
@endsection
