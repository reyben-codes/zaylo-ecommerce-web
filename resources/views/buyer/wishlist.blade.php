@extends('layouts.app')
@section('title', 'Wishlist · ZAYLO')
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-heart"></i><div><h1>Wishlist</h1><p>Your saved pieces, persisted to your account.</p></div></div></div>
<div class="page-content"><div class="product-grid">
@forelse($items as $item)<article class="product-card" data-product-url="{{ route('products.show', $item->product) }}"><a class="product-image" href="{{ route('products.show', $item->product) }}" aria-label="View {{ $item->product->name }}">@include('partials.product-card-image', ['product' => $item->product])</a><div class="product-info"><h2 class="product-name"><a href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a></h2><p>₱{{ number_format($item->product->price, 2) }}</p><form method="POST" action="{{ route('buyer.wishlist.toggle', $item->product) }}">@csrf<button class="text-button danger">Remove</button></form></div></article>
@empty<div class="placeholder-card"><i class="fas fa-heart"></i><h2>No saved products</h2><p>Use the heart button while browsing.</p></div>@endforelse
</div></div>
@endsection
