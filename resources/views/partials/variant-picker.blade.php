{{-- Option picker for a product with variations. Expects $variants, $selected (first in-stock variant), $label ("Color", "Size" or "Option"), $images (variant id => photo path), and optionally $formId of the buy form it submits with. --}}
@php
    // Swatch colors for common color words; options without a match show their photo or name only.
    $swatches = config('marketplace.color_swatches');
    $swatchFor = function (string $name) use ($swatches): ?string {
        foreach ($swatches as $word => $hex) {
            if (preg_match('/\b'.$word.'\b/i', $name)) {
                return $hex;
            }
        }

        return null;
    };
    $prices = $variants->map(fn ($variant) => (float) $variant->price);
    $showPrices = $prices->unique()->count() > 1;
@endphp

<fieldset class="variant-picker" data-variant-picker>
    <legend>{{ $label }}: <strong data-variant-selected>{{ $selected?->name }}</strong></legend>
    <div class="variant-picker__options">
        @foreach($variants as $variant)
            @php
                $soldOut = $variant->stock < 1;
                $photo = $images[$variant->id] ?? null;
                $swatch = $label === 'Color' && ! $photo ? $swatchFor($variant->name) : null;
            @endphp
            <label class="variant-option{{ $soldOut ? ' is-sold-out' : '' }}">
                <input type="radio" name="product_variant_id" value="{{ $variant->id }}" @isset($formId) form="{{ $formId }}" @endisset
                       data-name="{{ $variant->name }}" data-price="{{ $variant->price }}" data-regular="{{ $variant->regular_price }}"
                       data-stock="{{ $variant->stock }}" @if($photo) data-image="{{ $photo }}" @endif
                       @checked($selected?->id === $variant->id) @disabled($soldOut) required>
                <span class="variant-option__body">
                    @if($photo)
                        <img src="{{ $photo }}" alt="" class="variant-option__photo" loading="lazy">
                    @elseif($swatch)
                        <span class="variant-option__swatch" style="--swatch: {{ $swatch }}" aria-hidden="true"></span>
                    @endif
                    <span class="variant-option__text">
                        <span class="variant-option__name">{{ $variant->name }}</span>
                        @if($soldOut)
                            <span class="variant-option__tag">Sold out</span>
                        @elseif($showPrices)
                            <span class="variant-option__price">₱{{ number_format($variant->price, 2) }}</span>
                        @endif
                    </span>
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
