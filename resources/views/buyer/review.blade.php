@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-reviews.css') }}?v={{ filemtime(public_path('css/product-reviews.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/product-reviews.js') }}?v={{ filemtime(public_path('js/product-reviews.js')) }}" defer></script>
@endpush
@section('content')
<main class="page-content">
    <section class="review-compose">
        <a class="review-back-button" href="{{ route('buyer.orders') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m10 5-7 7 7 7M3 12h18"/></svg>
            <span>Back to my orders</span>
        </a>
        <h1>Review your purchase</h1>
        @php($reviewProductPhoto = $item->product->image_url ?: $item->product->images->first()?->path)
        <div class="review-product-summary">
            <img class="review-product-photo" src="{{ $reviewProductPhoto ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="{{ $reviewProductPhoto ? $item->product_name : 'Product photo unavailable' }}" width="112" height="112">
            <div>
                <p class="review-product-caption">You’re reviewing</p>
                <h2>{{ $item->product_name }}</h2>
                @if($item->variant_name)<p class="review-product-variant">{{ $item->variant_name }}</p>@endif
                <p class="review-product-variant">Quantity purchased: {{ $item->quantity }}</p>
            </div>
        </div>
        <form class="market-form" method="POST" action="{{ route('buyer.reviews.store', $item) }}" enctype="multipart/form-data">
            @csrf
            <fieldset class="review-rating" aria-describedby="rating-guide rating-scale"><legend>How would you rate this product?</legend>
                <div class="review-rating-stars">
                @foreach([1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'] as $value => $label)
                    <label class="review-star-choice" title="{{ $value }} {{ Str::plural('star', $value) }} — {{ $label }}">
                        <input type="radio" name="rating" value="{{ $value }}" data-rating-label="{{ $label }}" aria-label="{{ $value }} {{ Str::plural('star', $value) }} — {{ $label }}" required @checked((int) old('rating') === $value)>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.78 5.63L21 9.54l-4.5 4.39 1.06 6.2L12 17.2l-5.56 2.93 1.06-6.2L3 9.54l6.22-.91Z"/></svg>
                    </label>
                @endforeach
                </div>
                <p id="rating-guide" class="review-rating-guide" role="status">Select a star to rate your purchase.</p>
                <p id="rating-scale" class="review-rating-scale">1 — Poor · 2 — Fair · 3 — Good · 4 — Very good · 5 — Excellent</p>
            </fieldset>
            <label>Your review (optional)<textarea name="comment" maxlength="2000" rows="5" placeholder="Share the quality, fit, or your experience with this product.">{{ old('comment') }}</textarea></label>
            <label>Photos (optional)<input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" aria-describedby="review-photo-help"></label>
            <p id="review-photo-help">Up to 3 JPG, PNG or WebP photos, 5 MB each. Reviews and photos are public—avoid showing addresses or personal details.</p>
            <button class="market-button" type="submit">Publish review</button>
        </form>
    </section>
</main>
@endsection
