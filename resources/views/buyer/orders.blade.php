@extends('layouts.app')
@section('title', 'My Orders · ZAYLO')
@section('nav-links')<nav class="nav-links"><a href="{{ route('home') }}">Home</a><a href="{{ route('products.index') }}">Shop</a><a class="active-link" href="{{ route('buyer.orders') }}">Orders</a></nav>@endsection
@section('nav-icons')<a href="{{ route('buyer.cart') }}" aria-label="Shopping cart"><i class="fas fa-shopping-bag"></i></a>@endsection
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-box"></i><div><h1>My Orders</h1><p>Track purchases from placement through delivery.</p></div></div></div>
<div class="page-content">
@forelse($orders as $order)
<article class="order-card">
    <header><div><strong>{{ $order->order_number }}</strong><p>{{ $order->placed_at->format('M j, Y g:i A') }}</p></div><span class="status-pill status-{{ $order->status }}">{{ str($order->status)->replace('_', ' ')->title() }}</span></header>
    @foreach($order->items as $item)<p class="summary-row"><span>{{ $item->quantity }} × {{ $item->product_name }}</span><span>₱{{ number_format($item->line_total, 2) }}</span></p>@endforeach
    <footer><strong>Total: ₱{{ number_format($order->total, 2) }}</strong><span>{{ strtoupper($order->payment_method) }} · {{ ucfirst($order->payment?->status ?? 'pending') }}</span>
    @if(in_array($order->status, ['placed','confirmed']))<form method="POST" action="{{ route('buyer.orders.cancel', $order) }}">@csrf<button class="text-button danger">Cancel order</button></form>@endif</footer>
</article>
@empty<div class="placeholder-card"><i class="fas fa-box"></i><h2>No orders yet</h2><p>Your purchases will appear here.</p></div>@endforelse
{{ $orders->links() }}
</div>
@endsection
