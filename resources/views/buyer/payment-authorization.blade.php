@extends('layouts.app')
@section('title', 'Payment · ZAYLO')
@section('content')
<section class="page-content order-card">
    @if(in_array($payment->status, ['held', 'released'], true))
        <h1>Payment successful</h1>
        <p>Order {{ $payment->order->order_number }} · PHP {{ number_format($payment->amount_minor / 100, 2) }}</p>
        <p>{{ $payment->status === 'released' ? 'Your order is complete and payment has been settled.' : 'Your order is now To Ship. Payment is held until delivery is confirmed.' }}</p>
        <p>Reference {{ $payment->gateway_payment }}</p>
        <p>Return to the ZAYLO app and open My Orders to continue.</p>
        <a class="market-button" href="{{ route('buyer.orders') }}">View my orders</a>
    @else
        <h1>{{ session('payment_declined') ? 'Payment not completed' : 'Confirm payment' }}</h1>
        <p>Order {{ $payment->order->order_number }}</p>
        <p><strong>GCash · PHP {{ number_format($payment->amount_minor / 100, 2) }}</strong></p>
        @if(session('payment_declined'))<p>Your order is saved under To Pay. You can try again when ready.</p>@endif
        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        <p>Confirm this payment to continue with your order. Funds release after delivery confirmation.</p>
        <form method="POST" action="{{ $authorizeUrl }}">@csrf
            <button class="market-button" type="submit" name="decision" value="approve">Pay PHP {{ number_format($payment->amount_minor / 100, 2) }}</button>
            <button class="text-button" type="submit" name="decision" value="decline">Cancel payment</button>
        </form>
    @endif
</section>
@endsection
