@extends('layouts.payment')
@section('title', 'Payment · ZAYLO')
@section('content')
@php($paid = in_array($payment->status, ['held', 'released'], true))
<section class="payment-card" aria-labelledby="payment-title">
    <div class="payment-intro">
        <span class="payment-symbol {{ $paid ? 'payment-symbol--success' : '' }}" aria-hidden="true">
            @if($paid)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M7 15h4"/></svg>
            @endif
        </span>
        <p class="payment-eyebrow">{{ $paid ? 'Payment confirmed' : 'One last step' }}</p>
        <h1 id="payment-title">{{ $paid ? 'Payment successful' : (session('payment_declined') ? 'Payment not completed' : 'Confirm payment') }}</h1>
        <p class="payment-description">{{ $paid ? ($payment->status === 'released' ? 'Your order is complete and payment has been settled.' : 'You’re all set. Your order is now To Ship.') : 'Complete your payment to send this order to the seller.' }}</p>
    </div>
    <div class="payment-summary">
        <div class="payment-total"><span>{{ $paid ? 'Amount paid' : 'Amount to pay' }}</span><strong>PHP {{ number_format($payment->amount_minor / 100, 2) }}</strong></div>
        <dl class="payment-details">
            <div><dt>Order number</dt><dd>{{ $payment->order->order_number }}</dd></div>
            <div><dt>Payment method</dt><dd><span class="payment-method-icon" aria-hidden="true">G</span> GCash</dd></div>
            @if($paid)
                <div><dt>Status</dt><dd><span class="payment-status">{{ $payment->status === 'released' ? 'Settled' : 'Paid' }}</span></dd></div>
                <div><dt>Reference number</dt><dd class="payment-reference">{{ $payment->gateway_payment }}</dd></div>
            @else
                <div><dt>Status</dt><dd><span class="payment-status payment-status--pending">Awaiting payment</span></dd></div>
            @endif
        </dl>
    </div>
    @if(session('payment_declined') && ! $paid)
        <p class="payment-notice" role="status">No payment was completed. This checkout remains in To Pay and will not be sent for fulfillment until payment is confirmed.</p>
    @endif
    @if($errors->any())<p class="payment-error" role="alert">{{ $errors->first() }}</p>@endif
    <div class="payment-assurance">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/></svg>
        <p><strong>{{ $payment->status === 'released' ? 'Payment settled' : 'Held until delivery confirmation' }}</strong><span>{{ $payment->status === 'released' ? 'The payment has been allocated to the seller and delivery partner.' : 'Payment stays held until you confirm delivery or the return window ends.' }}</span></p>
    </div>
    @if($paid)
        <div class="payment-actions"><a class="payment-button" href="{{ route('buyer.orders') }}">View my orders <span aria-hidden="true">&rarr;</span></a></div>
        <p class="payment-help">Using the ZAYLO app? Return to the app and open My Orders.</p>
    @else
        <form class="payment-actions" method="POST" action="{{ $authorizeUrl }}">@csrf
            <button class="payment-button payment-button--gcash" type="submit" name="decision" value="approve">Pay PHP {{ number_format($payment->amount_minor / 100, 2) }} <span aria-hidden="true">&rarr;</span></button>
            <button class="payment-button payment-button--secondary" type="submit" name="decision" value="decline">Cancel payment</button>
        </form>
        <p class="payment-help">Your order can only proceed after payment is confirmed.</p>
    @endif
</section>
@endsection
