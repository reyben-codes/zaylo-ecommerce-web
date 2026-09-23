@extends('layouts.app')
@section('title', 'Checkout · ZAYLO')
@section('content')
@php
    $usableAddresses = $addresses->filter(fn ($address) => $address->isStructured());
    $selectedAddress = old('address_id', session('selected_address_id', $usableAddresses->first()?->id));
    $addressTypes = ['Home' => 'fa-house', 'Work' => 'fa-briefcase', 'School' => 'fa-graduation-cap', 'Other' => 'fa-location-dot'];
@endphp

<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-credit-card" aria-hidden="true"></i><div><h1>Checkout</h1><p>Review your order and delivery details before placing it.</p></div></div></div>

<div class="page-content checkout-page">
    <a class="checkout-back-link" href="{{ route('buyer.cart') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to cart</a>
    <section class="checkout-voucher-card" aria-labelledby="voucher-title">
        <div><h2 id="voucher-title"><i class="fas fa-ticket" aria-hidden="true"></i> Redeem a voucher</h2><p>Enter a voucher code to see your savings before ordering.</p></div>
        <form method="GET" action="{{ route('buyer.checkout.show') }}" class="checkout-voucher-form">
            <label class="sr-only" for="voucher-code">Voucher code</label>
            <input id="voucher-code" type="text" name="voucher" value="{{ $voucher?->code ?? '' }}" placeholder="Enter voucher code" maxlength="50" autocomplete="off">
            <button type="submit" class="text-button">Apply</button>
            @if($voucher)<a href="{{ route('buyer.checkout.show') }}" class="checkout-voucher-remove">Remove</a>@endif
        </form>
        @if($voucher)<p class="checkout-voucher-applied"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ $voucher->code }} applied: free shipping</p>@endif
        @error('voucher_code')<p class="cart-validation" role="alert">{{ $message }}</p>@enderror
    </section>

    <form method="POST" action="{{ route('buyer.checkout') }}" class="checkout-grid checkout-order-form">@csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
        @foreach($items as $item)<input type="hidden" name="cart_item_ids[]" value="{{ $item->id }}">@endforeach
        @if($voucher)<input type="hidden" name="voucher_code" value="{{ $voucher->code }}">@endif

        <div class="checkout-main-column">
            <section class="checkout-section-card" aria-labelledby="checkout-products-title">
                <div class="checkout-section-heading"><span class="checkout-step">1</span><div><h2 id="checkout-products-title">Your products</h2><p>{{ $items->sum('quantity') }} item(s) from {{ $items->pluck('product.seller_id')->unique()->count() }} seller(s)</p></div></div>
                @foreach($items as $item)
                    <div class="checkout-product-row">
                        <a href="{{ route('products.show', $item->product) }}" aria-label="View {{ $item->product->name }}"><img src="{{ $item->product->image_url ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="{{ $item->product->name }}"></a>
                        <div><strong><a href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a></strong><span>Sold by {{ $item->product->seller?->name ?? 'ZAYLO seller' }}</span>@if($item->variant)<span>{{ $item->variant->name }}</span>@endif<span>Qty {{ $item->quantity }} × &#8369;{{ number_format($item->unitPrice(), 2) }}</span></div>
                        <strong>&#8369;{{ number_format($item->unitPrice() * $item->quantity, 2) }}</strong>
                    </div>
                @endforeach
            </section>

            <section class="checkout-section-card" aria-labelledby="checkout-delivery-title">
                <div class="checkout-section-heading"><span class="checkout-step">2</span><div><h2 id="checkout-delivery-title">Buyer & delivery details</h2><p>Choose where your order should be delivered.</p></div></div>
                <div class="checkout-contact"><div><span>Buyer</span><strong>{{ auth()->user()->name }}</strong></div><div><span>Email</span><strong>{{ auth()->user()->email }}</strong></div></div>
                <h3>Delivery address</h3>
                @foreach($addressTypes as $type => $icon)
                    @php($typedAddresses = $addresses->where('label', $type))
                    @if($typedAddresses->isNotEmpty())
                        <fieldset class="checkout-address-group">
                            <legend><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $type }}</legend>
                            @foreach($typedAddresses as $address)
                                @if($address->isStructured())
                                    <label class="checkout-address-option"><input type="radio" name="address_id" value="{{ $address->id }}" @checked((string) $selectedAddress === (string) $address->id) required><span><strong>{{ $address->recipient_name }} @if($address->is_default)<small>Default</small>@endif</strong><em>{{ $address->phone }}</em>{{ $address->formatted() }}</span></label>
                                @else
                                    <div class="incomplete-address"><p>{{ $address->formatted() }}</p><a class="text-button incomplete-address-action" href="{{ route('addresses.edit', ['address' => $address, 'return_to' => 'checkout']) }}">Complete this address</a></div>
                                @endif
                            @endforeach
                        </fieldset>
                    @endif
                @endforeach
                @if($usableAddresses->isEmpty())<p class="cart-validation">Add a complete delivery address to place your order.</p>@endif
                <div class="checkout-address-actions"><a class="checkout-address-action checkout-address-action--primary" href="{{ route('addresses.create', ['return_to' => 'checkout']) }}"><i class="fas fa-plus" aria-hidden="true"></i> Add delivery address</a><a class="checkout-address-action checkout-address-action--secondary" href="{{ route('addresses.index') }}"><i class="far fa-address-book" aria-hidden="true"></i> Manage addresses</a></div>
                <div class="checkout-shipping-estimate"><i class="fas fa-truck-fast" aria-hidden="true"></i><div><strong>Estimated shipping date</strong><span>{{ $estimatedShipStart->format('M j') }}–{{ $estimatedShipEnd->format('M j, Y') }}</span><small>Estimated ship-out 2–4 business days after your order is placed. Delivery time may vary by seller and location.</small></div></div>
                <label class="checkout-notes-label">Delivery notes <span>(optional)</span><textarea name="notes" rows="3" maxlength="500" placeholder="Instructions for the seller or courier">{{ old('notes') }}</textarea></label>
            </section>

            <section class="checkout-section-card" aria-labelledby="checkout-payment-title">
                <div class="checkout-section-heading"><span class="checkout-step">3</span><div><h2 id="checkout-payment-title">Payment method</h2><p>Choose how you would like to pay.</p></div></div>
                <fieldset class="payment-method-options"><legend class="sr-only">Payment method</legend>
                    <label class="payment-method-option {{ old('payment_method', 'cod') === 'cod' ? 'selected' : '' }}"><input type="radio" name="payment_method" value="cod" @checked(old('payment_method', 'cod') === 'cod')><span class="payment-method-option-icon"><i class="fas fa-money-bill-wave" aria-hidden="true"></i></span><span class="payment-method-option-content"><span class="payment-method-option-title">Cash on Delivery</span><span class="payment-method-option-description">Pay when your order arrives</span></span></label>
                    <label class="payment-method-option {{ old('payment_method') === 'gcash' ? 'selected' : '' }}"><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')><span class="payment-method-option-icon"><i class="fas fa-mobile-alt" aria-hidden="true"></i></span><span class="payment-method-option-content"><span class="payment-method-option-title">GCash</span><span class="payment-method-option-description">Manual payment, pending confirmation</span></span></label>
                </fieldset>
            </section>
        </div>

        <aside class="checkout-card checkout-order-summary">
            <h2>Order summary</h2>
            <p class="summary-row"><span>Products ({{ $items->sum('quantity') }})</span><strong>&#8369;{{ number_format($subtotal, 2) }}</strong></p>
            <p class="summary-row"><span>Shipping ({{ $items->pluck('product.seller_id')->unique()->count() }} seller(s))</span><strong>&#8369;{{ number_format($shipping, 2) }}</strong></p>
            @if($shippingDiscount > 0)<p class="summary-row checkout-discount"><span>Free shipping voucher</span><strong>−&#8369;{{ number_format($shippingDiscount, 2) }}</strong></p>@endif
            <p class="summary-row checkout-total"><span>Total to pay</span><strong>&#8369;{{ number_format($subtotal + $shipping - $shippingDiscount, 2) }}</strong></p>
            <p class="checkout-summary-note">Your final total is recalculated when you place the order if prices or stock have changed.</p>
            <button class="market-button" type="submit" @disabled($usableAddresses->isEmpty())>Place order</button>
            <p class="checkout-summary-secure"><i class="fas fa-lock" aria-hidden="true"></i> Your order details are saved securely.</p>
        </aside>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', () => document.querySelectorAll('.payment-method-option').forEach(option => option.classList.toggle('selected', option.querySelector('input').checked)));
    });
});
</script>
@endsection
