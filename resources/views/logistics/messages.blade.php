@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Messages')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-chat.css') }}?v={{ filemtime(public_path('css/logistics-chat.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Messages</h1>
            <p>Coordinate pickups with sellers and deliveries with your riders. Pickup confirmations and declines are posted here too.</p>
        </div>
    </section>

    @include('partials.logistics-chat', [
        'pageRoute' => 'logistics.messages',
        'startRoute' => 'logistics.messages.start',
        'startField' => 'participant_id',
        'emptyText' => 'Start a conversation with a seller or one of your riders.',
    ])
@endsection
