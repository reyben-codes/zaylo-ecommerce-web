@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-reviews.css') }}?v={{ filemtime(public_path('css/product-reviews.css')) }}">
@endpush
@if(($reviewForm ?? null) === 'write')
    @push('scripts')
    <script src="{{ asset('js/product-reviews.js') }}?v={{ filemtime(public_path('js/product-reviews.js')) }}" defer></script>
    <script src="{{ asset('js/smooth-disclosure.js') }}?v={{ filemtime(public_path('js/smooth-disclosure.js')) }}" defer></script>
    @endpush
@endif
<section class="page-content" id="customer-reviews" aria-label="Customer reviews">
    <div class="product-reviews">
        <h2>Customer reviews</h2>
        @if($reviewCount)
            <p class="review-stars">★ {{ number_format($reviewAverage, 1) }}/5 · {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</p>
        @endif

        {{-- Any buyer can review the product here, bought or not (ProductReviewController::storeForProduct). --}}
        @if(($reviewForm ?? null) === 'write')
            <details class="review-write" data-smooth-disclosure @if($errors->hasAny(['rating', 'comment', 'photos', 'photos.*', 'review'])) open @endif>
                <summary class="order-review-link">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3 2.78 5.63L21 9.54l-4.5 4.39 1.06 6.2L12 17.2l-5.56 2.93 1.06-6.2L3 9.54l6.22-.91Z"/></svg>
                    <span>Write a review</span>
                </summary>
                <div data-disclosure-content>
                    <form class="market-form review-write__form" method="POST" action="{{ route('buyer.products.review', $product) }}" enctype="multipart/form-data">
                        @csrf
                        @include('partials.review-rating-stars', ['legend' => 'How would you rate this product?', 'prompt' => 'Select a star to rate this product.'])
                        <label>Your review (optional)<textarea name="comment" maxlength="2000" rows="4" placeholder="Share what you think of this product.">{{ old('comment') }}</textarea></label>
                        <label>Photos (optional)<input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" aria-describedby="review-photo-help"></label>
                        <p id="review-photo-help">Up to 3 JPG, PNG or WebP photos, 5 MB each. Reviews and photos are public—avoid showing addresses or personal details. Only reviews of delivered orders are marked “Verified purchase”.</p>
                        <button class="market-button" type="submit">Publish review</button>
                    </form>
                </div>
            </details>
        @elseif(($reviewForm ?? null) === 'written')
            <p class="review-write__note">Thanks for reviewing this product! Your review is below.</p>
        @elseif(($reviewForm ?? null) === 'login')
            <a class="order-review-link" href="{{ route('login') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3 2.78 5.63L21 9.54l-4.5 4.39 1.06 6.2L12 17.2l-5.56 2.93 1.06-6.2L3 9.54l6.22-.91Z"/></svg>
                <span>Log in to write a review</span>
            </a>
        @endif

        @forelse($reviews as $review)
            <article class="review-card">
                <strong>{{ Str::before($review->buyer->name, ' ') }}</strong>
                <span class="review-stars" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                <p class="review-meta">@if($review->isVerifiedPurchase())Verified purchase · @endif{{ $review->created_at->format('M j, Y') }}</p>
                @if($review->comment)<p>{{ $review->comment }}</p>@endif
                <div class="review-photos">
                    @foreach($review->photos ?? [] as $photo)
                        <a href="{{ Storage::disk('public')->url($photo) }}" target="_blank" rel="noopener"><img src="{{ Storage::disk('public')->url($photo) }}" alt="Customer photo {{ $loop->iteration }} for {{ $product->name }}" loading="lazy"></a>
                    @endforeach
                </div>
            </article>
        @empty
            <p>No reviews yet.{{ in_array($reviewForm ?? null, ['write', 'login'], true) ? ' Be the first to review this product.' : ' Buyers can rate this product after delivery from My Orders.' }}</p>
        @endforelse
        {{ $reviews->fragment('customer-reviews')->links() }}
    </div>
</section>
