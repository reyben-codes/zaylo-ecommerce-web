@extends('layouts.app')
@section('title', 'Shopping Cart · ZAYLO')
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-shopping-bag"></i><div><h1>Your Cart</h1><p>Review stock and delivery details before placing your order.</p></div></div></div>
<div @class(['page-content', 'checkout-grid', 'cart-empty-layout' => $cart->items->isEmpty()])>
    <section>
        @forelse($cart->items as $item)
        <article class="line-item">
            <img src="{{ $item->product->image_url ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="">
            <div class="line-item-main"><h2>{{ $item->product->name }}</h2><p>{{ $item->variant?->name }}</p><strong>₱{{ number_format($item->unitPrice(), 2) }}</strong></div>
            <form method="POST" action="{{ route('buyer.cart.update', $item) }}" class="quantity-form">@csrf @method('PATCH')<input type="number" name="quantity" min="1" max="{{ $item->availableStock() }}" value="{{ $item->quantity }}"><button>Update</button></form>
            <form method="POST" action="{{ route('buyer.cart.remove', $item) }}">@csrf @method('DELETE')<button class="text-button">Remove</button></form>
        </article>
        @empty
        <div class="cart-empty-state" aria-labelledby="empty-cart-title">
            <div class="cart-empty-icon" aria-hidden="true"><i class="fas fa-shopping-bag"></i></div>
            <h2 id="empty-cart-title">Your cart is empty</h2>
            <p>Discover your next everyday favorite. Add something you love and find it here when you're ready.</p>
            <a href="{{ route('products.index') }}" class="btn-primary cart-empty-button">
                Browse the collection <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        @endforelse
    </section>
    @if($cart->items->count())
    <aside class="checkout-card">
        <h2>Delivery & payment</h2>
        <p class="summary-row"><span>Subtotal</span><strong>₱{{ number_format($subtotal, 2) }}</strong></p>
        <p class="summary-row"><span>Shipping per seller</span><span>₱120.00</span></p>
        <form method="POST" action="{{ route('buyer.checkout') }}" class="market-form stacked-form">@csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            @php
                $usableAddresses = $addresses->filter(fn ($address) => $address->isStructured());
                $selectedAddress = old('address_id', session('selected_address_id', $usableAddresses->first()?->id));
                $addressTypes = ['Home' => 'fa-house', 'Work' => 'fa-briefcase', 'School' => 'fa-graduation-cap', 'Other' => 'fa-location-dot'];
            @endphp
            <h3>Delivery address</h3>
            @foreach($addressTypes as $type => $icon)
                @php($typedAddresses = $addresses->where('label', $type))
                @if($typedAddresses->isNotEmpty())
                    <fieldset class="checkout-address-group">
                        <legend><i class="fas {{ $icon }}"></i> {{ $type }}</legend>
                        @foreach($typedAddresses as $address)
                            @if($address->isStructured())
                                <label class="checkout-address-option">
                                    <input type="radio" name="address_id" value="{{ $address->id }}" @checked((string) $selectedAddress === (string) $address->id) required>
                                    <span><strong>{{ $address->recipient_name }} @if($address->is_default)<small>Default</small>@endif</strong><em>{{ $address->phone }}</em>{{ $address->formatted() }}</span>
                                </label>
                            @else
                                <div class="incomplete-address"><p>{{ $address->formatted() }}</p><a href="{{ route('addresses.edit', ['address' => $address, 'return_to' => 'checkout']) }}">Complete this address</a></div>
                            @endif
                        @endforeach
                    </fieldset>
                @endif
            @endforeach
            @if($usableAddresses->isEmpty())<p>Add a complete delivery address to place your order.</p>@endif
            <a href="{{ route('addresses.create', ['return_to' => 'checkout']) }}">Add delivery address</a>
            <a href="{{ route('addresses.index') }}">Manage saved addresses</a>
            <label>Delivery notes<textarea name="notes">{{ old('notes') }}</textarea></label>
            <p class="payment-note"><i class="fas fa-money-bill-wave"></i> Cash on delivery</p>
            <button class="market-button" type="submit" @disabled($usableAddresses->isEmpty())>Place order</button>
        </form>
    </aside>
    @endif
</div>
@endsection
