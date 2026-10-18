{{-- One size row of a color card on the add-product form. Expects $c (color index), $s (row index) and $row. --}}
<div class="product-form__variation" data-option-row>
    <span class="product-form__variation-name">
        <label class="sr-only" for="option-size-{{ $c }}-{{ $s }}">Size</label>
        <input type="text" id="option-size-{{ $c }}-{{ $s }}" name="options[{{ $c }}][sizes][{{ $s }}][size]" value="{{ $row['size'] ?? '' }}"
               maxlength="40" placeholder="Size (optional)" list="option-size-presets" autocomplete="off">
    </span>
    <label class="sr-only" for="option-price-{{ $c }}-{{ $s }}">Price</label>
    <input type="number" id="option-price-{{ $c }}-{{ $s }}" name="options[{{ $c }}][sizes][{{ $s }}][price]" value="{{ $row['price'] ?? '' }}"
           min="0" max="99999999.99" step="0.01" placeholder="Main" data-option-price>
    <label class="sr-only" for="option-stock-{{ $c }}-{{ $s }}">Stock</label>
    <input type="number" id="option-stock-{{ $c }}-{{ $s }}" name="options[{{ $c }}][sizes][{{ $s }}][stock]" value="{{ $row['stock'] ?? 0 }}"
           min="0" max="1000000" data-option-stock>
    <button type="button" class="product-form__remove product-form__remove--button" data-option-remove-row aria-label="Remove this size" hidden>
        <i class="fas fa-trash" aria-hidden="true"></i>
    </button>
</div>
