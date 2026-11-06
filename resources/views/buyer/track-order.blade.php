@extends('layouts.app')
@section('title', 'Track order '.$order->order_number.' · ZAYLO')
@include('partials.leaflet-assets')
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-map-location-dot" aria-hidden="true"></i><div><h1>Track your parcel</h1><p>Order {{ $order->order_number }}</p></div></div></div>
<div class="page-content">
    <a class="checkout-back-link" href="{{ route('buyer.orders') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to my orders</a>
    <section class="order-card track-map-card" data-order-tracking data-url="{{ route('buyer.orders.tracking', $order) }}"
             data-refresh-seconds="{{ $tracking['refresh_seconds'] }}" data-initial="{{ json_encode($tracking) }}">
        <div class="track-map-status" role="status" aria-live="polite">
            <i class="fas fa-truck-fast" aria-hidden="true"></i>
            <div>
                <strong data-tracking-stage>{{ $tracking['stage_label'] }}</strong>
                <p data-tracking-message>{{ $tracking['message'] }}</p>
                <p class="track-map-note" data-tracking-updated></p>
            </div>
        </div>
        <div class="zaylo-map zaylo-map--tall" data-map role="img" aria-label="Map of your delivery pin and your rider's location"></div>
        <ul class="track-map-legend">
            <li><span class="is-home" aria-hidden="true"></span>Your delivery pin</li>
            <li><span class="is-rider" aria-hidden="true"></span>Your rider</li>
        </ul>
        @unless($tracking['destination'])
            <p class="track-map-note">This order has no pinned location, so the rider uses the written address. Pin your saved addresses in <a href="{{ route('addresses.index') }}">Saved addresses</a> for your next orders.</p>
        @endunless
        <noscript><p class="track-map-note">Turn on JavaScript to see the map.</p></noscript>
    </section>
</div>
@endsection
@push('scripts')
    <script src="{{ asset('js/order-tracking.js') }}?v={{ filemtime(public_path('js/order-tracking.js')) }}" defer></script>
@endpush
