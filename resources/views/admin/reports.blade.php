@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    @php
        $maxSales = max(1, (float) $series->max('sales'));
        $percent = rtrim(rtrim(number_format($rate * 100, 2), '0'), '.');
    @endphp
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Finance</p>
            <h1>Reports</h1>
            <p>Marketplace sales and platform commission for orders placed {{ $range['label'] }} (Philippine time).</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('admin.reports.pdf', ['report' => 'sales', 'from' => $range['from'], 'to' => $range['to']]) }}" class="seller-button"><i class="fas fa-file-pdf" aria-hidden="true"></i> Sales summary PDF</a>
            <a href="{{ route('admin.reports.pdf', ['report' => 'commission', 'from' => $range['from'], 'to' => $range['to']]) }}" class="seller-button seller-button--secondary"><i class="fas fa-file-pdf" aria-hidden="true"></i> Commission PDF</a>
        </div>
    </section>

    <section class="seller-panel admin-filter-panel">
        @include('admin.partials.date-range', ['action' => route('admin.reports'), 'range' => $range])
    </section>

    <section class="seller-stat-grid" aria-label="Sales summary">
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Gross sales</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['gross'], 2) }}</strong>
            <span class="seller-stat__note">Merchandise in non-cancelled orders</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-receipt" aria-hidden="true"></i> Orders</span>
            <strong class="seller-stat__value">{{ number_format($summary['orders']) }}</strong>
            <span class="seller-stat__note">{{ $summary['completedOrders'] }} completed · {{ $summary['cancelled'] }} cancelled</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-basket-shopping" aria-hidden="true"></i> Average order</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['average'], 2) }}</strong>
            <span class="seller-stat__note">Including shipping</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-percent" aria-hidden="true"></i> Commission earned</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['commissionEarned'], 2) }}</strong>
            <span class="seller-stat__note">{{ $percent }}% of completed sales</span>
        </div>
    </section>

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
                    <thead><tr><th>Seller</th><th>Orders</th><th>Sales</th><th>Commission</th></tr></thead>
                    <tbody>
                        @forelse($bySeller->take(10) as $seller)
                            <tr><td>{{ $seller['seller'] }}</td><td>{{ $seller['orders'] }}</td><td>&#8369;{{ number_format($seller['sales'], 2) }}</td><td>&#8369;{{ number_format($seller['earned'] + $seller['settled'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="4"><p class="admin-empty-inline">No sales.</p></td></tr>
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
