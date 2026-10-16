@extends('layouts.seller')

@section('title', 'Logistics messages')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-chat.css') }}?v={{ filemtime(public_path('css/logistics-chat.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Logistics messages</h1>
            <p>Talk to the logistics partner about pickups and deliveries. Their pickup confirmations appear here.</p>
        </div>
    </section>

    @include('partials.logistics-chat', [
        'pageRoute' => 'seller.logistics-messages',
        'startRoute' => 'seller.logistics-messages.start',
        'startField' => 'provider_id',
        'emptyText' => 'Message a logistics partner about your pickups and deliveries.',
    ])
@endsection
