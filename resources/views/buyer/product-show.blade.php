@extends('layouts.app')
@section('title', $product->name.' · ZAYLO')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-sales.css') }}?v={{ filemtime(public_path('css/product-sales.css')) }}">
<link rel="stylesheet" href="{{ asset('css/product-variants.css') }}?v={{ filemtime(public_path('css/product-variants.css')) }}">
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
        {{-- Grayed when the whole product is sold out, or while showing a photo of a sold-out option. --}}
        <div class="detail-image{{ $availableStock <= 0 || ($mainImage['sold_out'] ?? false) ? ' is-sold-out' : '' }}" data-gallery-frame @if($availableStock <= 0) data-all-sold-out @endif>
            <img src="{{ $mainImage['path'] }}" alt="{{ $mainImage['alt_text'] }}" data-gallery-main>
            <span class="product-sold-out-label" aria-hidden="true" data-sold-out-label @unless($availableStock <= 0 || ($mainImage['sold_out'] ?? false)) hidden @endunless>Sold out</span>
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
                        class="detail-thumbnail{{ $loop->first ? ' is-active' : '' }}{{ $image['sold_out'] ? ' is-sold-out' : '' }}"
                        data-gallery-thumbnail
                        data-image-src="{{ $image['path'] }}"
                        data-image-alt="{{ $image['alt_text'] }}"
                        @if($image['sold_out']) data-sold-out @endif
                        aria-label="View image {{ $loop->iteration }} of {{ $galleryImages->count() }}{{ $image['sold_out'] ? ' (sold out)' : '' }}"
                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                    >
                        <img src="{{ $image['path'] }}" alt="" loading="lazy">
                        @if($image['sold_out'])
                            <span class="product-sold-out-label product-sold-out-label--small" aria-hidden="true">Sold out</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    </div>
    <section class="detail-copy">
        <div class="category-tags--detail">@include('partials.category-tags', ['href' => fn ($key) => route('products.index', ['category' => $key])])</div>
        @if($product->gender)<p class="product-category">For {{ $product->gender === 'unisex' ? 'everyone' : $product->gender }}</p>@endif
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
        @if($colorGroups)
            @include('partials.variant-picker-sizes', ['colorGroups' => $colorGroups, 'formId' => 'product-buy-form'])
        @elseif($product->variants->count())
            {{-- Shown to everyone so shoppers can browse colors and see which are sold out; it submits with the buy form below. --}}
            @include('partials.variant-picker', ['variants' => $product->variants, 'selected' => $displayVariant, 'label' => $optionLabel, 'images' => $variantImages, 'formId' => 'product-buy-form'])
        @endif
        @if(auth()->check() && auth()->user()->role === 'buyer')
        @if($availableStock > 0)
        <form data-cart-add method="POST" action="{{ route('buyer.cart.add', $product) }}" class="market-form product-buy-form" id="product-buy-form">
            @csrf
            <label for="product-quantity">Quantity</label>
            @include('partials.quantity-stepper', ['quantityId' => 'product-quantity', 'quantityMax' => $displayVariant?->stock ?? $product->stock])
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
@include('partials.product-reviews')
@endsection
