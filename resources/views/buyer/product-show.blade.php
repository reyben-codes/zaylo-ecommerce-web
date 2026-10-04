@extends('layouts.app')
@section('title', $product->name.' · ZAYLO')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-sales.css') }}?v={{ filemtime(public_path('css/product-sales.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/product-pricing.js') }}?v={{ filemtime(public_path('js/product-pricing.js')) }}" defer></script>
@endpush
@section('content')
@php
    $displayVariant = $product->variants->firstWhere('stock', '>', 0);
    $displayPrice = $displayVariant?->price ?? $product->price;
    $displayRegular = $displayVariant?->regular_price ?? ($product->original_price ?? $product->regular_price);
    $availableStock = $product->variants->isNotEmpty() ? $product->variants->sum('stock') : (\App\Models\Product::usesLegacySchema() ? $product->stock : 0);
@endphp
<main class="page-content product-detail">
    <div class="product-gallery" data-product-gallery role="region" aria-roledescription="carousel" aria-label="Photos of {{ $product->name }}">
        @php($mainImage = $galleryImages->first() ?? ['path' => asset('images/ZAYLO_ICON_DARK.png'), 'alt_text' => $product->name])
        <div class="detail-image">
            <img src="{{ $mainImage['path'] }}" alt="{{ $mainImage['alt_text'] }}" data-gallery-main>
            @if($galleryImages->count() > 1)
                <button type="button" class="detail-gallery-arrow detail-gallery-prev" data-gallery-prev aria-label="Previous product photo"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                <button type="button" class="detail-gallery-arrow detail-gallery-next" data-gallery-next aria-label="Next product photo"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            @endif
        </div>
        @if($galleryImages->count() > 1)
            <div class="detail-thumbnails" aria-label="Product images">
                @foreach($galleryImages as $image)
                    <button
                        type="button"
                        class="detail-thumbnail{{ $loop->first ? ' is-active' : '' }}"
                        data-gallery-thumbnail
                        data-image-src="{{ $image['path'] }}"
                        data-image-alt="{{ $image['alt_text'] }}"
                        aria-label="View image {{ $loop->iteration }} of {{ $galleryImages->count() }}"
                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                    >
                        <img src="{{ $image['path'] }}" alt="" loading="lazy">
                    </button>
                @endforeach
            </div>
        @endif
    </div>
    <section class="detail-copy">
        <p class="product-category">{{ $product->category_label }}@if($product->gender) · {{ ucfirst($product->gender) }}@endif</p>
        <h1>{{ $product->name }}</h1>
        <p class="detail-price" aria-live="polite"><span data-sale-price>₱{{ number_format($displayPrice, 2) }}</span>
            <del class="sale-price-original" data-sale-regular @if($displayPrice >= $displayRegular) hidden @endif>₱{{ number_format($displayRegular, 2) }}</del>
            <span class="sale-price-saving" data-sale-saving @if($displayPrice >= $displayRegular) hidden @endif>{{ $displayRegular > 0 ? round((1 - $displayPrice / $displayRegular) * 100) : 0 }}% off</span>
        </p>
        @if($product->saleIsActive())
            <p class="sale-period">Promotion for eligible options ends {{ $product->sale_ends_at->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A') }} (Philippine time).</p>
        @endif
        <section class="product-description" aria-labelledby="product-description-title">
            <h2 id="product-description-title">Product Description</h2>
            <p>{{ $product->description ?: 'The seller has not added a detailed description for this product yet.' }}</p>
        </section>
        <p class="stock-note">{{ $availableStock > 0 ? $availableStock.' available' : 'Currently sold out' }} @if($product->seller) · Sold by {{ $product->seller->name }} @endif</p>
        @if(auth()->check() && auth()->user()->role === 'buyer')
        @if($availableStock > 0)
        <form method="POST" action="{{ route('buyer.cart.add', $product) }}" class="market-form product-buy-form">
            @csrf
            @if($product->variants->count())
            <label>Option<select name="product_variant_id" required>
                @foreach($product->variants as $variant)<option value="{{ $variant->id }}" data-price="{{ $variant->price }}" data-regular="{{ $variant->regular_price }}" data-stock="{{ $variant->stock }}" @selected($displayVariant?->id === $variant->id) @disabled($variant->stock < 1)>{{ $variant->name }} — ₱{{ number_format($variant->price, 2) }} — {{ $variant->stock }} available</option>@endforeach
            </select></label>
            @endif
            <label>Quantity<input type="number" name="quantity" min="1" max="{{ $displayVariant?->stock ?? $product->stock }}" value="1" required></label>
            <div class="product-buy-actions">
                <button class="market-button product-add-cart-button" type="submit" name="purchase_action" value="add_to_cart">Add to Cart</button>
                <button class="market-button product-buy-now-button" type="submit" name="purchase_action" value="buy_now">Buy Now</button>
            </div>
        </form>
        @endif
        @if($product->seller)
            <form method="POST" action="{{ route('buyer.chat.start', $product) }}">
                @csrf
                <button type="submit" class="market-button inline-button"><i class="far fa-comment-dots" aria-hidden="true"></i> Chat with seller</button>
            </form>
        @endif
        @else
            <a href="{{ route('login') }}" class="market-button inline-button">Sign in to purchase</a>
        @endif
    </section>
</main>
@endsection
