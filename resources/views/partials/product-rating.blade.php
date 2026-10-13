@php
    $ratingCount = (int) $product->reviews_count;
    $ratingAverage = (float) $product->reviews_avg_rating;
@endphp
<a class="catalog-review-summary" href="{{ route('products.show', $product) }}#customer-reviews" aria-label="{{ $ratingCount ? number_format($ratingAverage, 1).' out of 5 stars, '.$ratingCount.' '.Str::plural('review', $ratingCount).'. Read customer reviews' : 'No reviews yet. View product' }}">
    <span class="catalog-review-stars" aria-hidden="true">{{ str_repeat('★', (int) round($ratingAverage)) }}{{ str_repeat('☆', 5 - (int) round($ratingAverage)) }}</span>
    @if($ratingCount)
        <strong>{{ number_format($ratingAverage, 1) }}</strong><span>({{ number_format($ratingCount) }} {{ Str::plural('review', $ratingCount) }})</span>
    @else
        <span>No reviews yet</span>
    @endif
</a>
