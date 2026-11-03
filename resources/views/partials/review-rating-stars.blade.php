{{-- The 1–5 star picker of a review form; public/js/product-reviews.js names the chosen rating. Expects $legend and $prompt. --}}
<fieldset class="review-rating" aria-describedby="rating-guide rating-scale"><legend>{{ $legend }}</legend>
    <div class="review-rating-stars">
    @foreach([1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'] as $value => $label)
        <label class="review-star-choice" title="{{ $value }} {{ Str::plural('star', $value) }} — {{ $label }}">
            <input type="radio" name="rating" value="{{ $value }}" data-rating-label="{{ $label }}" aria-label="{{ $value }} {{ Str::plural('star', $value) }} — {{ $label }}" required @checked((int) old('rating') === $value)>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.78 5.63L21 9.54l-4.5 4.39 1.06 6.2L12 17.2l-5.56 2.93 1.06-6.2L3 9.54l6.22-.91Z"/></svg>
        </label>
    @endforeach
    </div>
    <p id="rating-guide" class="review-rating-guide" role="status" data-rating-prompt="{{ $prompt }}">{{ $prompt }}</p>
    <p id="rating-scale" class="review-rating-scale">1 — Poor · 2 — Fair · 3 — Good · 4 — Very good · 5 — Excellent</p>
</fieldset>
