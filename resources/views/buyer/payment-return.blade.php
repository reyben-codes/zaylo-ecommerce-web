@extends('layouts.app')
@section('title', 'Payment status · ZAYLO')
@section('content')
<section class="page-content order-card">
    <h1>Check your payment status</h1>
    <p>Your order updates after the gateway verifies your payment. Return to the app and refresh My Orders, or open your orders below.</p>
    <a class="market-button" href="{{ route('buyer.orders') }}">View my orders</a>
</section>
@endsection
