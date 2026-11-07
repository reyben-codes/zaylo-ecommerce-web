@extends('layouts.app')
@section('title', 'Checkout · ZAYLO')
@section('content')
@php
    $usableAddresses = $addresses->filter(fn ($address) => $address->isStructured());
    $selectedAddress = old('address_id', session('selected_address_id', $usableAddresses->first()?->id));
    $addressTypes = ['Home' => 'fa-house', 'Work' => 'fa-briefcase', 'School' => 'fa-graduation-cap', 'Other' => 'fa-location-dot'];
    // The partner the buyer picked, or the one with the lowest fee to the chosen address.
    $rateTo = fn ($partner) => $partner['rates'][(int) $selectedAddress] ?? null;
    $selectedPartner = $shippingTable->firstWhere('id', (int) old('logistics_provider_id'))
        ?? $shippingTable->sortBy(fn ($partner) => $rateTo($partner)['fee'] ?? $standardFee)->first();
    $perSeller = $selectedPartner ? ($rateTo($selectedPartner)['fee'] ?? $standardFee) : $standardFee;
    $shipping = $perSeller * $sellerCount;
    $shippingDiscount = $voucher?->isFreeShipping() ? $shipping : 0;
    // A discount voucher only comes off the products of shops that joined it.
    $voucherDiscount = round(array_sum($voucherDiscounts), 2);
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
        @if($voucher)<p class="checkout-voucher-applied"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ $voucher->code }} applied: {{ $voucher->isFreeShipping() ? 'free shipping' : $voucher->describe().' on products from shops in this voucher (−₱'.number_format($voucherDiscount, 2).')' }}</p>@endif
        @error('voucher_code')<p class="cart-validation" role="alert">{{ $message }}</p>@enderror
    </section>

    {{-- Only products the seller marked 18+ need an approved ID; everything else checks out without one. --}}
    @if($identityItems->isNotEmpty())
        @php $adultNames = $identityItems->pluck('product.name')->unique()->implode(', '); @endphp
        <section class="identity-notice" role="status">
            <i class="fas fa-id-card" aria-hidden="true"></i>
            <div>
                @if($identityStatus === 'pending')
                    <strong>Your ID is being reviewed</strong>
                    <p>{{ $adultNames }} {{ $identityItems->count() === 1 ? 'is' : 'are' }} for adults (18+). You can order {{ $identityItems->count() === 1 ? 'it' : 'them' }} once an administrator approves your ID, or go back to your cart and leave {{ $identityItems->count() === 1 ? 'it' : 'them' }} out of this order.</p>
                @elseif($identityStatus === 'rejected')
                    <strong>Your ID was not approved</strong>
                    <p>{{ $adultNames }} {{ $identityItems->count() === 1 ? 'is' : 'are' }} for adults (18+). Upload a clearer photo of a valid ID, or go back to your cart and leave {{ $identityItems->count() === 1 ? 'it' : 'them' }} out of this order.</p>
                @else
                    <strong>18+ items need an approved ID</strong>
                    <p>{{ $adultNames }} {{ $identityItems->count() === 1 ? 'is' : 'are' }} for adults only. Upload a government-issued ID once to order 18+ items, or go back to your cart and leave {{ $identityItems->count() === 1 ? 'it' : 'them' }} out of this order.</p>
                @endif
            </div>
            @unless($identityStatus === 'pending')
                <a class="market-button inline-button" href="{{ route('buyer.account') }}#identity-verification">Upload ID</a>
            @endunless
        </section>
    @endif
    @error('identity')<p class="cart-validation" role="alert">{{ $message }}</p>@enderror

    <form method="POST" action="{{ route('buyer.checkout') }}" class="checkout-grid checkout-order-form">@csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
        @foreach($items as $item)
            <input type="hidden" name="cart_item_ids[]" value="{{ $item->id }}">
            <input type="hidden" name="quoted_unit_prices[{{ $item->id }}]" value="{{ number_format($item->unitPrice(), 2, '.', '') }}">
        @endforeach
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

            @if($shippingTable->isNotEmpty())
                <section class="checkout-section-card" aria-labelledby="checkout-partner-title">
                    <div class="checkout-section-heading">
                        <span class="checkout-step">3</span>
                        <div>
                            <h2 id="checkout-partner-title">Logistics partner</h2>
                            <p>The shipping fee depends on how far your address is from the partner's hub.</p>
                        </div>
                    </div>
                    <fieldset class="payment-method-options courier-options">
                        <legend class="sr-only">Choose a logistics partner</legend>
                        @foreach($shippingTable as $partner)
                            @php($partnerChecked = $selectedPartner && $selectedPartner['id'] === $partner['id'])
                            @php($rate = $rateTo($partner))
                            <label class="payment-method-option {{ $partnerChecked ? 'selected' : '' }}">
                                <input type="radio" name="logistics_provider_id" value="{{ $partner['id'] }}" @checked($partnerChecked) data-shipping-rates="{{ json_encode($partner['rates']) }}">
                                <span class="payment-method-option-icon"><i class="fas fa-warehouse" aria-hidden="true"></i></span>
                                <span class="payment-method-option-content">
                                    <span class="payment-method-option-title">{{ $partner['name'] }}</span>
                                    <span class="payment-method-option-description">@if($partner['hub'])Hub: {{ $partner['hub'] }} · @endif<span data-shipping-rate>&#8369;{{ number_format($rate['fee'] ?? $standardFee, 2) }} per seller · {{ $rate['label'] ?? 'Standard rate' }}</span></span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                </section>
            @endif

            <section class="checkout-section-card" aria-labelledby="checkout-courier-title">
                <div class="checkout-section-heading">
                    <span class="checkout-step">{{ $shippingTable->isNotEmpty() ? 4 : 3 }}</span>
                    <div>
                        <h2 id="checkout-courier-title">Delivery courier truck</h2>
                        <p>Choose which courier truck will pick up from the seller and deliver to your home with parcel scanning.</p>
                    </div>
                </div>
                <fieldset class="payment-method-options courier-options">
                    <legend class="sr-only">Choose delivery truck</legend>
                    @foreach(\App\Models\Order::COURIERS as $courierKey => $courier)
                        @php($courierChecked = old('courier_choice', \App\Models\Order::DEFAULT_COURIER) === $courierKey)
                        <label class="payment-method-option {{ $courierChecked ? 'selected' : '' }}">
                            <input type="radio" name="courier_choice" value="{{ $courierKey }}" @checked($courierChecked)>
                            <span class="payment-method-option-icon"><i class="fas {{ ['jnt_express' => 'fa-truck', 'flash_express' => 'fa-bolt-lightning'][$courierKey] ?? 'fa-truck-fast' }}" aria-hidden="true"></i></span>
                            <span class="payment-method-option-content">
                                <span class="payment-method-option-title">{{ $courier['title'] }}</span>
                                <span class="payment-method-option-description">{{ $courier['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
            </section>

            <section class="checkout-section-card" aria-labelledby="checkout-payment-title">
                <div class="checkout-section-heading"><span class="checkout-step">{{ $shippingTable->isNotEmpty() ? 5 : 4 }}</span><div><h2 id="checkout-payment-title">Payment method</h2><p>Choose how you would like to pay.</p></div></div>
                <fieldset class="payment-method-options"><legend class="sr-only">Payment method</legend>
                    <label class="payment-method-option {{ old('payment_method', 'cod') === 'cod' ? 'selected' : '' }}"><input type="radio" name="payment_method" value="cod" @checked(old('payment_method', 'cod') === 'cod')><span class="payment-method-option-icon"><i class="fas fa-money-bill-wave" aria-hidden="true"></i></span><span class="payment-method-option-content"><span class="payment-method-option-title">Cash on Delivery</span><span class="payment-method-option-description">Pay when your order arrives</span></span></label>
                    @php($gcashMissing = $gcashSellers->filter(fn ($seller) => ! $seller['account']))
                    <label class="payment-method-option {{ old('payment_method') === 'gcash' ? 'selected' : '' }} {{ $gcashMissing->isNotEmpty() ? 'is-unavailable' : '' }}"><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash') @disabled($gcashMissing->isNotEmpty())><span class="payment-method-option-icon"><i class="fas fa-mobile-alt" aria-hidden="true"></i></span><span class="payment-method-option-content"><span class="payment-method-option-title">GCash</span><span class="payment-method-option-description">{{ $gcashMissing->isNotEmpty() ? $gcashMissing->pluck('shop')->join(', ').' has not set up GCash yet' : "Send to each seller's GCash, then enter the reference number" }}</span></span></label>
                </fieldset>
                @if($gcashSellers->isNotEmpty() && $gcashMissing->isEmpty())
                    {{-- Shown when GCash is chosen (checkout-gcash.js). Each seller has their own GCash and their own order. --}}
                    <div class="gcash-pay" data-gcash-pay @if(old('payment_method') !== 'gcash') hidden @endif>
                        <p class="gcash-pay__intro"><i class="fas fa-circle-info" aria-hidden="true"></i> Send each seller's amount to their GCash, then type the 13-digit reference number from your GCash receipt. You can also add it later from My Orders. Unpaid GCash orders are cancelled after {{ \App\Services\GcashPayments::EXPIRE_HOURS }} hours.</p>
                        @foreach($gcashSellers as $seller)
                            <div class="gcash-pay__seller">
                                <div class="gcash-pay__account">
                                    <strong>{{ $seller['shop'] }}</strong>
                                    <span>GCash <b>{{ $seller['account']['number'] }}</b> · {{ $seller['account']['name'] }}</span>
                                    <span>Send @php($sellerDue = $seller['subtotal'] - ($voucherDiscounts[$seller['seller_id']] ?? 0))<b data-gcash-amount data-subtotal="{{ $sellerDue }}">&#8369;{{ number_format($sellerDue + $perSeller - ($voucher?->isFreeShipping() ? $perSeller : 0), 2) }}</b> <small>(items + shipping)</small></span>
                                </div>
                                <label class="gcash-pay__reference">
                                    <span>GCash reference no.</span>
                                    <input type="text" name="gcash_references[{{ $seller['seller_id'] }}]" value="{{ old('gcash_references.'.$seller['seller_id']) }}"
                                           inputmode="numeric" autocomplete="off" maxlength="25" placeholder="e.g. 1234 567 890123" pattern="[0-9 ]{13,25}">
                                </label>
                            </div>
                        @endforeach
                        @error('gcash_references.*')<p class="gcash-pay__error" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endif
            </section>
        </div>

        <aside class="checkout-card checkout-order-summary" data-checkout-summary data-subtotal="{{ $subtotal }}" data-sellers="{{ $sellerCount }}" data-standard-fee="{{ $standardFee }}" data-free-shipping="{{ $voucher?->isFreeShipping() ? 1 : 0 }}" data-voucher-discount="{{ $voucherDiscount }}">
            <h2>Order summary</h2>
            <p class="summary-row"><span>Products ({{ $items->sum('quantity') }})</span><strong>&#8369;{{ number_format($subtotal, 2) }}</strong></p>
            <p class="summary-row"><span>Shipping ({{ $sellerCount }} seller(s))</span><strong data-summary-shipping>&#8369;{{ number_format($shipping, 2) }}</strong></p>
            @if($voucher?->isFreeShipping())<p class="summary-row checkout-discount"><span>Free shipping voucher</span><strong data-summary-discount>−&#8369;{{ number_format($shippingDiscount, 2) }}</strong></p>@endif
            @if($voucherDiscount > 0)<p class="summary-row checkout-discount"><span>Voucher {{ $voucher->code }}</span><strong>−&#8369;{{ number_format($voucherDiscount, 2) }}</strong></p>@endif
            <p class="summary-row checkout-total"><span>Total to pay</span><strong data-summary-total>&#8369;{{ number_format($subtotal + $shipping - $shippingDiscount - $voucherDiscount, 2) }}</strong></p>
            <p class="checkout-summary-note">Your final total is recalculated when you place the order if prices or stock have changed.</p>
            <button class="market-button" type="submit" @disabled($usableAddresses->isEmpty() || $identityItems->isNotEmpty())>Place order</button>
            @if($identityItems->isNotEmpty())<p class="checkout-summary-note">18+ items need an approved ID before you can order them.</p>@endif
            <p class="checkout-summary-secure"><i class="fas fa-lock" aria-hidden="true"></i> Your order details are saved securely.</p>
        </aside>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    ['payment_method', 'courier_choice', 'logistics_provider_id'].forEach(name => {
        document.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
            radio.addEventListener('change', () => {
                radio.closest('fieldset').querySelectorAll('.payment-method-option').forEach(option => {
                    option.classList.toggle('selected', option.querySelector('input').checked);
                });
            });
        });
    });
});
</script>
<script src="{{ asset('js/checkout-shipping.js') }}?v={{ filemtime(public_path('js/checkout-shipping.js')) }}" defer></script>
<script src="{{ asset('js/checkout-gcash.js') }}?v={{ filemtime(public_path('js/checkout-gcash.js')) }}" defer></script>
@endsection
