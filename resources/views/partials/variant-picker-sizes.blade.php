{{-- Color, then size: each color lists only its own sizes. Expects $colorGroups (name, photo, stock, variants) and optionally $formId of the buy form the size submits with.
     Without JavaScript every color's sizes are listed under their own heading; product-pricing.js shows only the chosen color's sizes. --}}
@php
    $swatches = config('marketplace.color_swatches');
    $swatchFor = function (string $name) use ($swatches): ?string {
        foreach ($swatches as $word => $hex) {
            if (preg_match('/\b'.$word.'\b/i', $name)) {
                return $hex;
            }
        }

        return null;
    };
    $selectedColor = $colorGroups->search(fn ($group) => $group['stock'] > 0);
    $selectedColor = $selectedColor === false ? 0 : $selectedColor;
@endphp

<div class="variant-picker variant-picker--sized" data-variant-picker data-sized-picker>
    <fieldset class="variant-picker__group">
        <legend>Color: <strong data-color-selected>{{ $colorGroups[$selectedColor]['name'] }}</strong></legend>
        <div class="variant-picker__options">
            @foreach($colorGroups as $index => $group)
                @php $colorSoldOut = $group['stock'] < 1; @endphp
                <label class="variant-option{{ $colorSoldOut ? ' is-sold-out' : '' }}">
                    {{-- Choosing a color only filters the sizes, so sold-out colors stay selectable to see their photos. --}}
                    <input type="radio" name="variant_color" value="{{ $index }}" data-color-option data-name="{{ $group['name'] }}"
                           @if($group['photo']) data-image="{{ $group['photo'] }}" @endif @checked($index === $selectedColor)>
                    <span class="variant-option__body">
                        @if($group['photo'])
                            <img src="{{ $group['photo'] }}" alt="" class="variant-option__photo" loading="lazy">
                        @elseif($swatch = $swatchFor($group['name']))
                            <span class="variant-option__swatch" style="--swatch: {{ $swatch }}" aria-hidden="true"></span>
                        @endif
                        <span class="variant-option__text">
                            <span class="variant-option__name">{{ $group['name'] }}</span>
                            @if($colorSoldOut)
                                <span class="variant-option__tag">Sold out</span>
                            @endif
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
    </fieldset>

    @foreach($colorGroups as $index => $group)
        @php $showPrices = $group['variants']->map(fn ($variant) => (float) $variant->price)->unique()->count() > 1; @endphp
        <fieldset class="variant-picker__group variant-picker__sizes" data-size-group="{{ $index }}">
            <legend>Size<span class="variant-picker__for"> for {{ $group['name'] }}</span>: <strong data-size-selected>Select a size</strong></legend>
            <div class="variant-picker__options">
                @foreach($group['variants'] as $variant)
                    @php $soldOut = $variant->stock < 1; @endphp
                    <label class="variant-option variant-option--size{{ $soldOut ? ' is-sold-out' : '' }}">
                        <input type="radio" name="product_variant_id" value="{{ $variant->id }}" @isset($formId) form="{{ $formId }}" @endisset
                               data-name="{{ $variant->size_label ?? $variant->name }}" data-price="{{ $variant->price }}" data-regular="{{ $variant->regular_price }}"
                               data-stock="{{ $variant->stock }}" @if($soldOut) data-sold-out @endif @disabled($soldOut) required>
                        <span class="variant-option__body">
                            <span class="variant-option__text">
                                <span class="variant-option__name">{{ $variant->size_label ?? $variant->name }}</span>
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
    @endforeach
</div>
