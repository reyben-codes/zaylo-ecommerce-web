@extends('layouts.seller')

@section('auto-refresh', 'on')
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
            @if($shopPage)
                <a href="{{ $shopPage }}" class="seller-button seller-button--secondary" target="_blank" rel="noopener"><i class="fas fa-store" aria-hidden="true"></i> View my shop page</a>
            @endif
            <a href="{{ route('seller.orders', ['status' => 'to_prepare']) }}" class="seller-button seller-button--secondary"><i class="fas fa-box" aria-hidden="true"></i> Orders to prepare</a>
            <a href="{{ route('seller.products.create') }}" class="seller-button"><i class="fas fa-plus" aria-hidden="true"></i> Add product</a>
        </div>
    </section>

    @include('seller.partials.compliance-warnings')

    <section class="seller-stat-grid seller-stat-grid--five" aria-label="Shop summary">
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
        @if($shopPage)
            <a href="{{ $shopPage }}" class="seller-stat" target="_blank" rel="noopener">
                <span class="seller-stat__label"><i class="fas fa-user-group" aria-hidden="true"></i> Followers</span>
                <strong class="seller-stat__value">{{ number_format($followers) }}</strong>
                <span class="seller-stat__note">Buyers following your shop page</span>
            </a>
        @else
            <article class="seller-stat">
                <span class="seller-stat__label"><i class="fas fa-user-group" aria-hidden="true"></i> Followers</span>
                <strong class="seller-stat__value">{{ number_format($followers) }}</strong>
                <span class="seller-stat__note">Your shop page opens once your account is approved</span>
            </article>
        @endif
    </section>

    @php
        $shopRank = $rankings['shop']['rank'];
        $topShops = \App\Services\SellerRankings::TOP_SHOPS;
    @endphp
    <section class="seller-panel seller-ranking" aria-labelledby="ranking-title">
        <header class="seller-panel__header">
            <div>
                <h2 id="ranking-title">Your ranking on ZAYLO</h2>
                <p>Last {{ \App\Services\SellerRankings::DAYS }} days, by sales across all shops</p>
            </div>
        </header>
        <div class="seller-ranking__body">
            <div class="seller-ranking__shop{{ $shopRank === 1 ? ' is-best' : ($shopRank && $shopRank <= $topShops ? ' is-top' : '') }}">
                <i class="fas {{ $shopRank === 1 ? 'fa-trophy' : 'fa-ranking-star' }}" aria-hidden="true"></i>
                <div>
                    @if($shopRank === 1)
                        <strong>Best shop on ZAYLO</strong>
                        <span>Your shop has the most sales of all {{ $rankings['shop']['shops'] }} shops.</span>
                    @elseif($shopRank)
                        <strong>#{{ $shopRank }} of {{ $rankings['shop']['shops'] }} shops{{ $shopRank <= $topShops ? ' · Top '.$topShops : '' }}</strong>
                        <span>&#8369;{{ number_format($rankings['shop']['sales'], 2) }} in sales.</span>
                    @else
                        <strong>Not ranked yet</strong>
                        <span>Your shop is ranked once it makes a sale.</span>
                    @endif
                </div>
            </div>

            <div class="seller-ranking__products">
                <h3>Best-selling products</h3>
                @if($rankings['products']->isEmpty())
                    <p class="seller-cell-note">Products you sell in this period appear here.</p>
                @else
                    <ol class="dashboard-list">
                        @foreach($rankings['products'] as $product)
                            <li class="dashboard-list__item">
                                <div class="dashboard-list__main">
                                    <strong>{{ $product['name'] }}</strong>
                                    <span>{{ number_format($product['quantity']) }} sold</span>
                                </div>
                                @if($product['rank'])
                                    <span class="stock-pill seller-ranking__pill{{ $product['rank'] === 1 ? ' is-best' : '' }}">{{ $product['rank'] === 1 ? 'Best seller on ZAYLO' : '#'.$product['rank'].' on ZAYLO' }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
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
