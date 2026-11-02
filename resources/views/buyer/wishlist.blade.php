@extends('layouts.app')
@section('title', 'Wishlist · ZAYLO')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/shop-page.css') }}?v={{ filemtime(public_path('css/shop-page.css')) }}">
@endpush
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-heart"></i><div><h1>Wishlist</h1><p>Your saved pieces, persisted to your account.</p></div></div></div>
<div class="page-content">
<div class="wishlist-actions"><a href="{{ route('buyer.following') }}" class="market-button market-button--secondary"><i class="fas fa-store" aria-hidden="true"></i> Shops Followed</a></div>
<div class="product-grid">
@forelse($items as $item)
    @include('partials.shop-card', ['product' => $item->product, 'heading' => 'h2'])
@empty<div class="placeholder-card"><i class="fas fa-heart"></i><h2>No saved products</h2><p>Use the heart button while browsing.</p></div>@endforelse
</div></div>
@endsection
