@extends('layouts.app')
@section('title', 'Shopping Cart · ZAYLO')
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-shopping-bag" aria-hidden="true"></i><div><h1>Your Cart</h1><p>Choose what to buy, update quantities, or remove items.</p></div></div></div>

<div @class(['page-content', 'checkout-grid', 'cart-empty-layout' => $cart->items->isEmpty(), 'cart-page'])>
    <section>
        @if($cart->items->isNotEmpty())
            <form id="cart-selection-form" method="POST" action="{{ route('buyer.cart.selection') }}">@csrf</form>
            <div class="cart-toolbar">
                <label class="cart-select-all"><input type="checkbox" id="cart-select-all" checked> Select all <span>({{ $cart->items->count() }})</span></label>
                <button class="text-button danger" type="submit" form="cart-selection-form" name="action" value="remove" id="cart-remove-selected"><i class="far fa-trash-can" aria-hidden="true"></i> Remove selected</button>
            </div>
            @error('cart_item_ids')<p class="cart-validation" role="alert">{{ $message }}</p>@enderror
            @error('cart_item_ids.*')<p class="cart-validation" role="alert">{{ $message }}</p>@enderror
        @endif

        @forelse($cart->items as $item)
            <article class="line-item cart-product" data-cart-price="{{ $item->unitPrice() * $item->quantity }}" data-cart-quantity="{{ $item->quantity }}">
                <label class="cart-item-select" for="cart-item-{{ $item->id }}"><span class="sr-only">Select {{ $item->product->name }}</span><input id="cart-item-{{ $item->id }}" class="cart-item-checkbox" type="checkbox" name="cart_item_ids[]" value="{{ $item->id }}" form="cart-selection-form" checked></label>
                <a href="{{ route('products.show', $item->product) }}" class="cart-product-image"><img src="{{ $item->product->image_url ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="{{ $item->product->name }}"></a>
                <div class="line-item-main">
                    <p class="cart-product-seller">{{ $item->product->seller?->name ?? 'ZAYLO seller' }}</p>
                    <h2><a href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a></h2>
                    @if($item->variant)<p>{{ $item->variant->name }}</p>@endif
                    <strong>&#8369;{{ number_format($item->unitPrice(), 2) }} each</strong>
                    @if(!$item->product->is_active || $item->availableStock() < $item->quantity)
                        <p class="cart-stock-warning">{{ $item->product->is_active ? 'Only '.$item->availableStock().' left in stock.' : 'This product is unavailable.' }}</p>
                    @endif
                    <div class="cart-item-actions">
                        <form method="POST" action="{{ route('buyer.cart.update', $item) }}" class="quantity-form">@csrf @method('PATCH')<label class="sr-only" for="quantity-{{ $item->id }}">Quantity for {{ $item->product->name }}</label><input id="quantity-{{ $item->id }}" type="number" name="quantity" min="1" max="{{ max(1, $item->availableStock()) }}" value="{{ $item->quantity }}"><button type="submit">Update</button></form>
                        <a class="text-button" href="{{ route('products.show', $item->product) }}">View product</a>
                        <form method="POST" action="{{ route('buyer.cart.remove', $item) }}" class="cart-remove-form">@csrf @method('DELETE')<button class="cart-remove-button" type="submit"><i class="far fa-trash-can" aria-hidden="true"></i> Remove</button></form>
                    </div>
                </div>
                <strong class="cart-line-total">&#8369;{{ number_format($item->unitPrice() * $item->quantity, 2) }}</strong>
            </article>
        @empty
            <div class="cart-empty-state" aria-labelledby="empty-cart-title">
                <div class="cart-empty-icon" aria-hidden="true"><i class="fas fa-shopping-bag"></i></div>
                <h2 id="empty-cart-title">Your cart is empty</h2>
                <p>Discover your next everyday favorite. Add something you love and find it here when you're ready.</p>
                <a href="{{ route('products.index') }}" class="btn-primary cart-empty-button">Browse the collection <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        @endforelse
    </section>

    @if($cart->items->isNotEmpty())
        <aside class="checkout-card cart-summary">
            <h2>Cart summary</h2>
            <p class="summary-row"><span>Selected items <span id="cart-selected-count">({{ $cart->items->sum('quantity') }})</span></span><strong id="cart-selected-subtotal">&#8369;{{ number_format($subtotal, 2) }}</strong></p>
            <p class="cart-summary-note">Shipping and vouchers are calculated on the checkout page.</p>
            <button class="market-button" type="submit" form="cart-selection-form" name="action" value="checkout" id="cart-checkout-button">Proceed to checkout</button>
            <a href="{{ route('products.index') }}" class="cart-continue-link">Continue shopping</a>
        </aside>
    @endif
</div>

@if($cart->items->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const boxes = Array.from(document.querySelectorAll('.cart-item-checkbox'));
    const selectAll = document.getElementById('cart-select-all');
    const count = document.getElementById('cart-selected-count');
    const subtotal = document.getElementById('cart-selected-subtotal');
    const buttons = [document.getElementById('cart-remove-selected'), document.getElementById('cart-checkout-button')];
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const refresh = () => {
        const selected = boxes.filter(box => box.checked);
        const quantity = selected.reduce((sum, box) => sum + Number(box.closest('.cart-product').dataset.cartQuantity), 0);
        const total = selected.reduce((sum, box) => sum + Number(box.closest('.cart-product').dataset.cartPrice), 0);
        count.textContent = `(${quantity})`;
        subtotal.textContent = money.format(total);
        selectAll.checked = selected.length === boxes.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
        buttons.forEach(button => button.disabled = selected.length === 0);
    };
    selectAll.addEventListener('change', () => { boxes.forEach(box => box.checked = selectAll.checked); refresh(); });
    boxes.forEach(box => box.addEventListener('change', refresh));
    refresh();
});
</script>
@endif
@endsection
