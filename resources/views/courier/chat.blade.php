@extends('layouts.app')

@section('title', 'ZAYLO · Courier Chat')

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('courier.dashboard') }}" class="{{ request()->routeIs('courier.dashboard') ? 'active-link' : '' }}">Dashboard</a>
    <a href="{{ route('courier.deliveries') }}" class="{{ request()->routeIs('courier.deliveries') ? 'active-link' : '' }}">Deliveries</a>
    <a href="{{ route('courier.earnings') }}" class="{{ request()->routeIs('courier.earnings') ? 'active-link' : '' }}">Earnings</a>
    <a href="{{ route('courier.history') }}" class="{{ request()->routeIs('courier.history') ? 'active-link' : '' }}">History</a>
</div>
@endsection
@section('nav-icons')
<a href="{{ route('courier.chat') }}"><i class="fas fa-comment-dots"></i></a>
<a href="{{ route('courier.account') }}"><i class="far fa-user"></i></a>
@endsection


@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-chat.css') }}?v={{ filemtime(public_path('css/logistics-chat.css')) }}">
@endpush

@section('content')
<div class="logistics-chat-standalone">
    <div class="logistics-chat-standalone__heading">
        <h1>Messages</h1>
        <p>{{ $rider?->provider ? 'Your conversation with '.$rider->provider->name.', your logistics partner.' : 'Messages open once a logistics partner approves your rider application.' }}</p>
    </div>
    @if($rider?->provider)
        @include('partials.logistics-chat', ['pageRoute' => 'courier.chat', 'showList' => false, 'emptyText' => 'Your logistics partner has not been set up for messages yet.'])
    @else
        <div class="seller-panel"><div class="seller-empty"><i class="fas fa-hourglass-half" aria-hidden="true"></i><strong>Waiting for approval</strong><span>You can message your logistics partner after they approve you.</span></div></div>
    @endif
</div>
@endsection
