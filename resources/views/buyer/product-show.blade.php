@extends('layouts.app')
@section('title', $product->name.' · ZAYLO')
@section('content')
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
        <p class="detail-price">₱{{ number_format($product->price, 2) }}</p>
        <section class="product-description" aria-labelledby="product-description-title">
            <h2 id="product-description-title">Product Description</h2>
            <p>{{ $product->description ?: 'The seller has not added a detailed description for this product yet.' }}</p>
        </section>
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
            <div class="product-buy-actions">
                <button class="market-button product-add-cart-button" type="submit" name="purchase_action" value="add_to_cart"><i class="fas fa-shopping-bag" aria-hidden="true"></i> Add to Cart</button>
                <button class="market-button product-buy-now-button" type="submit" name="purchase_action" value="buy_now"><i class="fas fa-bolt" aria-hidden="true"></i> Buy Now</button>
            </div>
        </form>
        @else
            <a href="{{ route('login') }}" class="market-button inline-button">Sign in to purchase</a>
        @endif
    </section>
</main>
@endsection
