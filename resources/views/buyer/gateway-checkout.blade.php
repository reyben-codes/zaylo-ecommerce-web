@extends('layouts.payment')
@section('title', 'GCash checkout · ZAYLO')
@section('content')
<section class="payment-card" aria-labelledby="payment-title">
    <div class="payment-intro">
        <p class="payment-eyebrow">Awaiting payment</p>
        <h1 id="payment-title">Continue to GCash</h1>
        <p class="payment-description">Complete payment to place your order. It stays in To Pay until payment is confirmed.</p>
    </div>
    <div class="payment-summary">
        <div class="payment-total"><span>Amount to pay</span><strong>PHP {{ number_format($order->total, 2) }}</strong></div>
        <dl class="payment-details"><div><dt>Order number</dt><dd>{{ $order->order_number }}</dd></div></dl>
    </div>
    <p class="payment-notice">Payment stays held until delivery is confirmed or the return window ends.</p>
    @if($testMode)<p class="payment-notice">Test mode — no real money moves.</p>@endif
    @if($errors->any())<p class="payment-error" role="alert">{{ $errors->first() }}</p>@endif
    <form class="payment-actions" method="POST" action="{{ route('escrow.pay', $order) }}" @unless($errors->any()) data-gateway-checkout @endunless>
        @csrf
        <button class="payment-button" type="submit">Continue to GCash &rarr;</button>
        <a class="payment-button payment-button--secondary" href="{{ route('buyer.orders', ['status' => 'to_pay']) }}">View unpaid orders</a>
    </form>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/gateway-checkout.js') }}" defer></script>@endpush
