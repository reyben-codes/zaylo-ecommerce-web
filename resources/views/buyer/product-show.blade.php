@extends('layouts.app')
@section('title', $product->name.' · ZAYLO')
@section('nav-links')
<nav class="nav-links"><a href="{{ route('home') }}">Home</a><a href="{{ route('products.index') }}">Shop</a><a href="{{ route('products.index', ['category' => $product->category]) }}">{{ config('marketplace.categories.'.$product->category, str($product->category)->replace('_', ' ')->title()) }}</a></nav>
@endsection
@section('nav-icons')
@if(auth()->check() && auth()->user()->role === 'buyer')<a href="{{ route('buyer.cart') }}" aria-label="Shopping cart"><i class="fas fa-shopping-bag"></i></a>@endif
@endsection
@section('content')
<main class="page-content product-detail">
    <div class="detail-image"><img src="{{ $product->image_url ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="{{ $product->name }}"></div>
    <section class="detail-copy">
        <p class="product-category">{{ config('marketplace.categories.'.$product->category, str($product->category)->replace('_', ' ')->title()) }}@if($product->gender) · {{ ucfirst($product->gender) }}@endif</p>
        <h1>{{ $product->name }}</h1>
        <p class="detail-price">₱{{ number_format($product->price, 2) }}</p>
        <p>{{ $product->description }}</p>
        <p class="stock-note">{{ $product->stock }} available @if($product->seller) · Sold by {{ $product->seller->name }} @endif</p>
        @if(auth()->check() && auth()->user()->role === 'buyer')
        <form method="POST" action="{{ route('buyer.cart.add', $product) }}" class="market-form product-buy-form">
            @csrf
            @if($product->variants->count())
            <label>Option<select name="product_variant_id" required>
                @foreach($product->variants as $variant)<option value="{{ $variant->id }}">{{ $variant->name }} — {{ $variant->stock }} available</option>@endforeach
            </select></label>
            @endif
            <label>Quantity<input type="number" name="quantity" min="1" max="{{ $product->stock }}" value="1" required></label>
            <button class="market-button" type="submit">Add to cart</button>
        </form>
        @else
            <a href="{{ route('login') }}" class="market-button inline-button">Sign in to purchase</a>
        @endif
    </section>
</main>
@endsection
