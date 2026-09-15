@extends('layouts.app')
@section('title', $product->name.' · ZAYLO')
@section('content')
<main class="page-content product-detail">
    <div class="product-gallery" data-product-gallery>
        @php($mainImage = $galleryImages->first() ?? ['path' => asset('images/ZAYLO_ICON_DARK.png'), 'alt_text' => $product->name])
        <div class="detail-image">
            <img src="{{ $mainImage['path'] }}" alt="{{ $mainImage['alt_text'] }}" data-gallery-main>
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

@push('scripts')
<script>
document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
    const mainImage = gallery.querySelector('[data-gallery-main]');

    gallery.querySelectorAll('[data-gallery-thumbnail]').forEach((thumbnail) => {
        thumbnail.addEventListener('click', () => {
            mainImage.src = thumbnail.dataset.imageSrc;
            mainImage.alt = thumbnail.dataset.imageAlt;

            gallery.querySelectorAll('[data-gallery-thumbnail]').forEach((item) => {
                const isActive = item === thumbnail;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        });
    });
});
</script>
@endpush
