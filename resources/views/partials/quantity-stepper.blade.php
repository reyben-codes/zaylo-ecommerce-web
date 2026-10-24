<div class="quantity-stepper" data-quantity-stepper>
    <button type="button" data-quantity-change="-1" aria-label="Decrease quantity" hidden>−</button>
    <input id="{{ $quantityId }}" type="number" name="quantity" min="1" max="{{ max(1, (int) $quantityMax) }}" step="1" inputmode="numeric" value="{{ $quantityValue ?? 1 }}" required>
    <button type="button" data-quantity-change="1" aria-label="Increase quantity" hidden>+</button>
</div>
