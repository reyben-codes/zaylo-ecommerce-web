{{-- One color card on the add-product form: an optional color name and its sizes. Expects $c (color index) and $group. --}}
@php
    $colorName = trim((string) ($group['color'] ?? ''));
    $rows = array_values((array) ($group['sizes'] ?? [])) ?: [['size' => '', 'price' => '', 'stock' => 0]];
@endphp
<details class="size-group" data-option-color data-smooth-disclosure open>
    <summary class="size-group__summary">
        <span class="size-group__swatch size-group__swatch--plain" data-option-swatch aria-hidden="true"><i class="fas fa-palette" aria-hidden="true"></i></span>
        <span class="size-group__title">
            <strong data-option-title>{{ $colorName !== '' ? $colorName : 'Sizes' }}</strong>
            <small data-option-summary>Size, price and stock</small>
        </span>
        <span></span>
        <i class="fas fa-chevron-down size-group__chevron" aria-hidden="true"></i>
    </summary>
    <div class="size-group__body" data-disclosure-content>
        <div class="size-group__color">
            <label for="option-color-{{ $c }}">Color name</label>
            <input type="text" id="option-color-{{ $c }}" name="options[{{ $c }}][color]" value="{{ $colorName }}" maxlength="40"
                   placeholder="e.g. Black — leave empty for sizes only" list="color-presets" autocomplete="off" data-option-color-name>
        </div>
        <div class="product-form__variation-head" aria-hidden="true"><span>Size</span><span>Price (&#8369;)</span><span>Stock</span><span></span></div>
        <div class="product-form__option-rows" data-option-rows>
            @foreach($rows as $s => $row)
                @include('seller.partials.new-option-row', ['c' => $c, 's' => $s, 'row' => $row])
            @endforeach
        </div>
        <div class="product-form__size-actions" data-size-actions hidden>
            <button type="button" class="product-form__size-button" data-option-add-size><i class="fas fa-plus" aria-hidden="true"></i> Add size</button>
            <button type="button" class="product-form__size-button" data-option-fill-sizes hidden><i class="fas fa-ruler" aria-hidden="true"></i> Standard sizes</button>
            <button type="button" class="product-form__size-button product-form__size-button--danger" data-option-remove-color><i class="fas fa-trash" aria-hidden="true"></i> Remove color</button>
        </div>
    </div>
</details>
