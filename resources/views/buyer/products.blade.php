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
            @php($inStock = $product->active_variants_count > 0 ? $product->catalog_variant_stock > 0 : (\App\Models\Product::usesLegacySchema() && $product->stock > 0))
            <article class="product-card" data-product-url="{{ route('products.show', $product) }}">
                <a href="{{ route('products.show', $product) }}" class="product-image" aria-label="View {{ $product->name }}">
                    @include('partials.product-card-image', ['product' => $product])
                    @if($product->badge)<span class="product-badge">{{ $product->badge }}</span>@endif
                </a>
                <div class="product-info">
                    <p class="product-category">{{ $product->category_label }}</p>
                    <h2 class="product-name"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h2>
                    <div class="product-price"><span class="current-price">₱{{ number_format($product->price, 2) }}</span>@if($product->original_price > $product->price)<del class="original-price">₱{{ number_format($product->original_price, 2) }}</del><small>{{ $product->discount_percentage }}% off</small>@endif</div>
                    <div class="card-actions">
                        @if(!$inStock)
                            <a class="btn-add-cart" href="{{ route('products.show', $product) }}">View product · Sold out</a>
                        @else
                        @if(auth()->check() && auth()->user()->role === 'buyer')
                            @if($product->active_variants_count)
                                <a class="btn-add-cart" href="{{ route('products.show', $product) }}">Choose options</a>
                            @else
                                <form method="POST" action="{{ route('buyer.cart.add', $product) }}">@csrf<input type="hidden" name="quantity" value="1"><button class="btn-add-cart">Add to cart</button></form>
                            @endif
                            <form method="POST" action="{{ route('buyer.wishlist.toggle', $product) }}">@csrf<button class="icon-button" aria-label="Save {{ $product->name }} to wishlist"><i class="far fa-heart"></i></button></form>
                        @else
                            <a class="btn-add-cart" href="{{ route('login') }}">Sign in to buy</a>
                        @endif
                        @endif
                    </div>
                    <p class="catalog-stock {{ $inStock ? '' : 'is-sold-out' }}">{{ $inStock ? 'In stock' : 'Currently sold out' }}</p>
                </div>
            </article>
            @endforeach
        </div>
        <div class="pagination-wrap">{{ $products->links() }}</div>
    @else
        <div class="placeholder-card catalog-empty"><i class="fas fa-search" aria-hidden="true"></i><h2>No products match your search</h2><p>Try fewer keywords, a wider price range, or remove a filter above.</p><a href="{{ route('products.index') }}" class="market-button">Browse available products</a></div>
    @endif
</div>
@endsection
