@extends('layouts.app')

@section('title', 'Shop · ZAYLO')

@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/catalog-filters.css') }}?v={{ filemtime(public_path('css/catalog-filters.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/smooth-disclosure.js') }}?v={{ filemtime(public_path('js/smooth-disclosure.js')) }}" defer></script>
@endpush
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-tags"></i><div>
    <h1>{{ $category ? config('marketplace.categories.'.$category, str($category)->replace('_', ' ')->title()) : 'Shop All' }}</h1>
    <p>Explore products from approved sellers across the ZAYLO marketplace.</p>
</div></div></div>
<div class="page-content">
    @include('partials.catalog-filters')

    @if($products->count())
        <div class="product-grid">
            @foreach($products as $product)
            @include('partials.shop-card', ['product' => $product, 'heading' => 'h2', 'showStock' => true])
            @endforeach
        </div>
        <div class="pagination-wrap">{{ $products->links() }}</div>
    @else
        <div class="placeholder-card catalog-empty"><i class="fas fa-search" aria-hidden="true"></i><h2>No products match your search</h2><p>Try fewer keywords, a wider price range, or remove a filter above.</p><a href="{{ route('products.index') }}" class="market-button">Browse available products</a></div>
    @endif
</div>
@endsection
