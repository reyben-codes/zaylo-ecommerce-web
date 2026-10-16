@extends('layouts.seller')

@section('title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-dashboard.css') }}?v={{ filemtime(public_path('css/seller-dashboard.css')) }}">
@endpush

@section('content')
    @php
        $openOrders = $orderCounts['to_prepare'] + $orderCounts['shipping'];
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $storeName }}</p>
            <h1>Welcome back, {{ Str::before($user->name, ' ') ?: $user->name }}</h1>
            <p>Here is what needs your attention today.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('seller.orders', ['status' => 'to_prepare']) }}" class="seller-button seller-button--secondary"><i class="fas fa-box" aria-hidden="true"></i> Orders to prepare</a>
            <a href="{{ route('seller.products.create') }}" class="seller-button"><i class="fas fa-plus" aria-hidden="true"></i> Add product</a>
        </div>
    </section>

    @include('seller.partials.compliance-warnings')

    <section class="seller-stat-grid" aria-label="Shop summary">
        <a href="{{ route('seller.orders') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-box" aria-hidden="true"></i> Open orders</span>
            <strong class="seller-stat__value">{{ number_format($openOrders) }}</strong>
            <span class="seller-stat__note">{{ $orderCounts['to_prepare'] }} to prepare · {{ $orderCounts['shipping'] }} in delivery</span>
        </a>
        <a href="{{ route('seller.reports') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Delivered sales</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($revenue, 2) }}</strong>
            <span class="seller-stat__note">&#8369;{{ number_format($monthRevenue, 2) }} this month</span>
        </a>
        <a href="{{ route('seller.products') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Products</span>
            <strong class="seller-stat__value">{{ number_format($productCount) }}</strong>
            @if($lowStockCount + $outOfStockCount > 0)
                <span class="seller-stat__note seller-stat__note--warning">{{ $lowStockCount }} low · {{ $outOfStockCount }} out of stock</span>
            @else
                <span class="seller-stat__note">{{ $activeProductCount }} visible to buyers</span>
            @endif
        </a>
        <article class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-star" aria-hidden="true"></i> Rating</span>
            <strong class="seller-stat__value">{{ $rating['average'] !== null ? number_format($rating['average'], 1) : '—' }}</strong>
            <span class="seller-stat__note">{{ $rating['count'] ? $rating['count'].' '.Str::plural('review', $rating['count']) : 'No reviews yet' }}</span>
        </article>
    </section>

    <div class="seller-panel-grid">
        <section class="seller-panel">
            <header class="seller-panel__header">
                <div>
                    <h2>Recent orders</h2>
                    <p>{{ $orderCounts['all'] }} {{ Str::plural('order', $orderCounts['all']) }} in total</p>
                </div>
                <a href="{{ route('seller.orders') }}" class="seller-link">View all <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </header>

            @if($recentOrders->isEmpty())
                <div class="seller-empty">
                    <i class="fas fa-receipt" aria-hidden="true"></i>
                    <strong>No orders yet</strong>
                    <span>New orders from buyers will show up here.</span>
                </div>
            @else
                <ul class="dashboard-list">
                    @foreach($recentOrders as $order)
                        <li class="dashboard-list__item">
                            <div class="dashboard-list__main">
                                <strong>{{ $order['reference'] }}</strong>
                                <span>{{ $order['buyer'] }} · {{ $order['date']?->timezone('Asia/Manila')->format('M j, Y') }}</span>
                            </div>
                            <span class="order-status order-status--{{ str($order['status'])->replace('_', '-') }}">{{ $statusLabels[$order['status']] ?? str($order['status'])->headline() }}</span>
                            <strong class="dashboard-list__amount">&#8369;{{ number_format($order['total'], 2) }}</strong>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="seller-panel">
            <header class="seller-panel__header">
                <div>
                    <h2>Needs restocking</h2>
                    <p>Products at or below their low-stock alert</p>
                </div>
                <a href="{{ route('seller.inventory', ['stock' => 'low']) }}" class="seller-link">Inventory <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </header>

            @if($restockProducts->isEmpty())
                <div class="seller-empty">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <strong>Stock looks healthy</strong>
                    <span>No products are running low right now.</span>
                </div>
            @else
                <ul class="dashboard-list">
                    @foreach($restockProducts as $product)
                        <li class="dashboard-list__item">
                            <div class="dashboard-list__main">
                                <strong>{{ $product['name'] }}</strong>
                                <span>{{ $product['category'] }}</span>
                            </div>
                            <span class="stock-pill stock-pill--{{ $product['stock'] > 0 ? 'low' : 'out' }}">{{ $product['stock'] > 0 ? $product['stock'].' left' : 'Out of stock' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
