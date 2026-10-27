@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Messages')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-chat.css') }}?v={{ filemtime(public_path('css/logistics-chat.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Platform</p>
            <h1>Messages</h1>
            <p>Help & support conversations with buyers, sellers, riders and logistics partners. Any admin can answer; users see replies from "ZAYLO support".</p>
        </div>
    </section>

    @include('partials.logistics-chat', [
        'pageRoute' => 'admin.chat',
        'startRoute' => 'admin.chat.start',
        'startField' => 'participant_id',
        'fetchRoute' => 'support.messages',
        'sendRoute' => 'support.send',
        'photos' => true,
        'emptyText' => 'When someone writes to ZAYLO support, the conversation appears here. You can also start one.',
    ])
@endsection
