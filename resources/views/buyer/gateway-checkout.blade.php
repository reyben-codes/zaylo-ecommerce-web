@extends('layouts.app')
@section('title', 'Secure GCash checkout · ZAYLO')
@section('content')
<section class="page-content order-card">
    <h1>Continue to secure GCash checkout</h1>
    <p>Order {{ $order->order_number }} · PHP {{ number_format($order->total, 2) }}</p>
    <p>Authorize payment on the gateway's page. Your order moves to To Ship after verification. Funds release after delivery confirmation.</p>
    @if($testMode)<p>Test mode — no real money moves.</p>@endif
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('escrow.pay', $order) }}" @unless($errors->any()) data-gateway-checkout @endunless>@csrf<button class="market-button" type="submit">Continue to GCash</button></form>
    <a href="{{ route('buyer.orders', ['status' => 'to_pay']) }}">View all unpaid orders</a>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/gateway-checkout.js') }}" defer></script>@endpush
