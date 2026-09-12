@extends('layouts.app')
@section('title', 'Wishlist · ZAYLO')
@section('nav-links')<nav class="nav-links"><a href="{{ route('home') }}">Home</a><a href="{{ route('products.index') }}">Shop</a><a class="active-link" href="{{ route('buyer.wishlist') }}">Wishlist</a></nav>@endsection
@section('nav-icons')<a href="{{ route('buyer.cart') }}" aria-label="Shopping cart"><i class="fas fa-shopping-bag"></i></a>@endsection
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-heart"></i><div><h1>Wishlist</h1><p>Your saved pieces, persisted to your account.</p></div></div></div>
<div class="page-content"><div class="product-grid">
@forelse($items as $item)<article class="product-card"><a class="product-image" href="{{ route('products.show', $item->product) }}"><img src="{{ $item->product->image_url }}" alt="{{ $item->product->name }}"></a><div class="product-info"><h2 class="product-name">{{ $item->product->name }}</h2><p>₱{{ number_format($item->product->price, 2) }}</p><form method="POST" action="{{ route('buyer.wishlist.toggle', $item->product) }}">@csrf<button class="text-button danger">Remove</button></form></div></article>
@empty<div class="placeholder-card"><i class="fas fa-heart"></i><h2>No saved products</h2><p>Use the heart button while browsing.</p></div>@endforelse
</div></div>
@endsection
