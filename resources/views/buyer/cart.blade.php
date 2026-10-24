@extends('layouts.app')
@section('title', 'Shopping Cart · ZAYLO')
@section('content')
<div class="page-hero"><div class="page-hero-inner">@include('partials.icons.cart', ['class' => 'page-hero-svg'])<div><h1>Your Cart</h1><p>Choose what to buy, update quantities, or remove items.</p></div></div></div>

<div @class(['page-content', 'checkout-grid', 'cart-empty-layout' => $cart->items->isEmpty(), 'cart-page'])>
    <section>
        @if($cart->items->isNotEmpty())
            <form id="cart-selection-form" method="POST" action="{{ route('buyer.cart.selection') }}">@csrf</form>
            {{-- Filled with every changed quantity by cart.js when "Update all" is used. --}}
            <form id="cart-update-all-form" method="POST" action="{{ route('buyer.cart.update-all') }}">@csrf @method('PATCH')</form>
            <div class="cart-toolbar">
                <div class="cart-toolbar-start">
                    <label class="cart-select-all"><input type="checkbox" id="cart-select-all" checked> Select all <span>({{ $cart->items->count() }})</span></label>
                    <button class="cart-update-all" type="submit" form="cart-update-all-form" id="cart-update-all" hidden disabled>
                        <i class="fas fa-rotate" aria-hidden="true"></i> Update all <span data-update-count></span>
                    </button>
                </div>
                <button class="text-button danger" type="submit" form="cart-selection-form" name="action" value="remove" id="cart-remove-selected"><i class="far fa-trash-can" aria-hidden="true"></i> Remove selected</button>
            </div>
            @error('cart_item_ids')<p class="cart-validation" role="alert">{{ $message }}</p>@enderror
            @error('cart_item_ids.*')<p class="cart-validation" role="alert">{{ $message }}</p>@enderror
            @if($errors->has('quantities') || $errors->has('quantities.*'))
                <div class="cart-validation" role="alert">
                    @foreach(array_merge($errors->get('quantities'), ...array_values($errors->get('quantities.*'))) as $message)<p>{{ $message }}</p>@endforeach
                </div>
            @endif
        @endif

        @forelse($cart->items as $item)
            <article class="line-item cart-product" data-cart-item-id="{{ $item->id }}" data-cart-price="{{ $item->unitPrice() * $item->quantity }}" data-cart-quantity="{{ $item->quantity }}">
                <label class="cart-item-select" for="cart-item-{{ $item->id }}"><span class="sr-only">Select {{ $item->product->name }}</span><input id="cart-item-{{ $item->id }}" class="cart-item-checkbox" type="checkbox" name="cart_item_ids[]" value="{{ $item->id }}" form="cart-selection-form" checked></label>
                <a href="{{ route('products.show', $item->product) }}" class="cart-product-image"><img src="{{ $item->product->image_url ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="{{ $item->product->name }}"></a>
                <div class="line-item-main">
                    <p class="cart-product-seller">{{ $item->product->seller?->name ?? 'ZAYLO seller' }}</p>
                    <h2><a href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a></h2>
                    @if($item->variant)<p>{{ $item->variant->name }}</p>@endif
                    <strong>&#8369;{{ number_format($item->unitPrice(), 2) }} each</strong>
                    @if(!$item->product->is_listed || $item->availableStock() < $item->quantity)
                        <p class="cart-stock-warning">{{ $item->product->is_listed ? 'Only '.$item->availableStock().' left in stock.' : 'This product is unavailable.' }}</p>
                    @endif
                </div>
                    <div class="cart-item-actions">
                        <form method="POST" action="{{ route('buyer.cart.update', $item) }}" class="quantity-form">
                            @csrf @method('PATCH')
                            <label class="sr-only" for="quantity-{{ $item->id }}">Quantity for {{ $item->product->name }}</label>
                            @include('partials.quantity-stepper', ['quantityId' => 'quantity-'.$item->id, 'quantityMax' => $item->availableStock(), 'quantityValue' => $item->quantity])
                            <button type="submit" data-quantity-update hidden disabled>Update</button>
                            <noscript><button type="submit">Update</button></noscript>
                        </form>
                        <a class="text-button" href="{{ route('products.show', $item->product) }}">View product</a>
                        <form method="POST" action="{{ route('buyer.cart.remove', $item) }}" class="cart-remove-form">@csrf @method('DELETE')<button class="cart-remove-button" type="submit"><i class="far fa-trash-can" aria-hidden="true"></i> Remove</button></form>
                    </div>
                <strong class="cart-line-total">&#8369;{{ number_format($item->unitPrice() * $item->quantity, 2) }}</strong>
            </article>
        @empty
            <div class="cart-empty-state" aria-labelledby="empty-cart-title">
                <div class="cart-empty-icon" aria-hidden="true">@include('partials.icons.cart', ['class' => 'cart-empty-svg'])</div>
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

@push('scripts')
<script src="{{ asset('js/cart.js') }}?v={{ filemtime(public_path('js/cart.js')) }}" defer></script>
@endpush
@endsection
