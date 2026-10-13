@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-reviews.css') }}?v={{ filemtime(public_path('css/product-reviews.css')) }}">
@endpush
<section class="page-content" id="customer-reviews" aria-label="Customer reviews">
    <div class="product-reviews">
        <h2>Customer reviews</h2>
        @if($reviewCount)
            <p class="review-stars">★ {{ number_format($reviewAverage, 1) }}/5 · {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</p>
        @endif
        @forelse($reviews as $review)
            <article class="review-card">
                <strong>{{ Str::before($review->buyer->name, ' ') }}</strong>
                <span class="review-stars" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                <p class="review-meta">Verified purchase · {{ $review->created_at->format('M j, Y') }}</p>
                @if($review->comment)<p>{{ $review->comment }}</p>@endif
                <div class="review-photos">
                    @foreach($review->photos ?? [] as $photo)
                        <a href="{{ Storage::disk('public')->url($photo) }}" target="_blank" rel="noopener"><img src="{{ Storage::disk('public')->url($photo) }}" alt="Customer photo {{ $loop->iteration }} for {{ $product->name }}" loading="lazy"></a>
                    @endforeach
                </div>
            </article>
        @empty
            <p>No reviews yet. Buyers can rate this product after delivery from My Orders.</p>
        @endforelse
        {{ $reviews->fragment('customer-reviews')->links() }}
    </div>
</section>
