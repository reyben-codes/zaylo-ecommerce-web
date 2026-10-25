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

    {{-- public/js/live-search.js swaps this region as the buyer types in the catalog search. --}}
    <div class="catalog-live-results" id="catalog-live-results" data-live-catalog-results>
    <div class="catalog-results-heading" id="catalog-results">
        <p role="status"><strong>{{ number_format($products->total()) }}</strong> {{ Str::plural('product', $products->total()) }} found @if($products->count()) <span>· Showing {{ $products->firstItem() }}–{{ $products->lastItem() }}</span>@endif</p>
        <a class="catalog-reset-button" href="{{ route('products.index') }}">Reset search & filters</a>
    </div>
    @if($filterChips)
        <nav class="catalog-filter-chips" aria-label="Applied filters">
            @foreach($filterChips as $chip)<a href="{{ $chip['url'] }}" aria-label="Remove {{ $chip['label'] }}">{{ $chip['label'] }} <span aria-hidden="true">×</span></a>@endforeach
        </nav>
    @endif

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
</div>
@endsection
