@extends('layouts.payment')
@section('title', 'Payment status · ZAYLO')
@section('content')
<section class="payment-card" aria-labelledby="payment-title">
    <div class="payment-intro">
        <p class="payment-eyebrow">Payment verification</p>
        <h1 id="payment-title">Check your payment status</h1>
        <p class="payment-description">Your order only proceeds after the gateway confirms payment. Return to the app and refresh My Orders, or check your orders below.</p>
    </div>
    <div class="payment-actions"><a class="payment-button" href="{{ route('buyer.orders') }}">View my orders &rarr;</a></div>
</section>
@endsection
