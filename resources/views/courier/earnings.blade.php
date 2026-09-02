@extends('layouts.app')

@section('title', 'ZAYLO · Earnings')

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


@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-money-bill-wave"></i>
        <div>
            <h1></h1>
            <p></p>
        </div>
    </div>
</div>
<div class="page-content">
    <div class="placeholder-card">
        <i class="fas fa-money-bill-wave"></i>
        <h2>My Earnings</h2>
        <p>Track your delivery earnings.</p>
    </div>
</div>
@endsection
