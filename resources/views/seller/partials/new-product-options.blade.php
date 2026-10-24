{{-- Colors & sizes on the add-product form, saved together with the product. Blank cards and rows are ignored. --}}
@php
    $optionGroups = array_values((array) old('options', [])) ?: [['color' => '', 'sizes' => [['size' => '', 'price' => '', 'stock' => 0]]]];
    $optionError = $errors->first('options') ?: collect($errors->get('options.*'))->flatten()->first();
@endphp
<section class="seller-panel" aria-labelledby="options-heading">
    <header class="seller-panel__header">
        <div>
            <h2 id="options-heading">Colors &amp; sizes <span class="product-form__optional">(optional)</span></h2>
            <p>Sell this product in colors, sizes, or both. Each option gets its own price and stock. Leave it empty for a single item.</p>
        </div>
    </header>
    <div class="seller-panel__body product-form__variations" data-new-options data-size-presets="{{ json_encode(config('marketplace.size_presets')) }}" data-kids-size-presets="{{ json_encode(config('marketplace.kids_size_presets')) }}" data-kids-genders="{{ json_encode(config('marketplace.kids_genders')) }}" data-next-color="{{ count($optionGroups) }}">
        <div class="color-labels" data-option-chips hidden>
            <span class="product-form__chips-label">Quick add:</span>
            @foreach(config('marketplace.color_presets') as $preset)
                <button type="button" class="color-label" data-option-chip="{{ $preset }}">
                    <span class="color-label__swatch" style="--swatch: {{ \App\Models\ProductVariant::swatchFor($preset) }}" aria-hidden="true"></span>
                    {{ $preset }}
                </button>
            @endforeach
        </div>

        <div class="product-form__option-cards" data-option-cards>
            @foreach($optionGroups as $c => $group)
                @include('seller.partials.new-option-card', ['c' => $c, 'group' => $group])
            @endforeach
        </div>

        <div class="product-form__size-actions" data-size-actions hidden>
            <button type="button" class="product-form__size-button" data-option-add-color><i class="fas fa-plus" aria-hidden="true"></i> Add another color</button>
        </div>

        <p class="seller-field__error" role="alert" @if(! $optionError) hidden @endif data-option-error>{{ $optionError }}</p>
        <p class="product-form__size-hint">A color without sizes is sold as one option. Leave a price empty to use the main price above.</p>

        <template data-option-card-template>
            @include('seller.partials.new-option-card', ['c' => '__C__', 'group' => ['color' => '', 'sizes' => [['size' => '', 'price' => '', 'stock' => 0]]]])
        </template>
        <template data-option-row-template>
            @include('seller.partials.new-option-row', ['c' => '__C__', 's' => '__S__', 'row' => ['size' => '', 'price' => '', 'stock' => 0]])
        </template>
        <datalist id="color-presets">
            @foreach(config('marketplace.color_presets') as $preset)
                <option value="{{ $preset }}"></option>
            @endforeach
        </datalist>
        <datalist id="option-size-presets"></datalist>
    </div>
</section>
