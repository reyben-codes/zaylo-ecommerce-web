@extends('layouts.app')
@section('title', 'Seller Dashboard · ZAYLO')
@section('nav-links')<nav class="nav-links"><a class="active-link" href="{{ route('seller.dashboard') }}">Dashboard</a><a href="{{ route('seller.products') }}">Products</a><a href="{{ route('seller.orders') }}">Orders</a><a href="{{ route('seller.inventory') }}">Inventory</a></nav>@endsection
@section('nav-icons')<a href="{{ route('seller.account') }}" aria-label="Account"><i class="far fa-user"></i></a>@endsection
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-store"></i><div><h1>Welcome back, {{ $user->name }}</h1><p>Your live store and fulfilment overview.</p></div></div></div>
<div class="page-content"><div class="metric-grid"><div class="metric-card"><span>Products</span><strong>{{ $productCount }}</strong></div><div class="metric-card"><span>Open orders</span><strong>{{ $openOrderCount }}</strong></div><div class="metric-card"><span>Low stock</span><strong>{{ $lowStockCount }}</strong></div><div class="metric-card"><span>Completed revenue</span><strong>₱{{ number_format($revenue, 2) }}</strong></div></div><div class="quick-actions"><a class="market-button inline-button" href="{{ route('seller.products') }}">Add a product</a><a class="market-button secondary inline-button" href="{{ route('seller.orders') }}">Process orders</a></div></div>
@endsection
