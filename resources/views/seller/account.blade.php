@extends('layouts.app')

@section('title', 'ZAYLO · Seller Account')

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('seller.dashboard') }}" class="{{ request()->routeIs('seller.dashboard') ? 'active-link' : '' }}">Dashboard</a>
    <a href="{{ route('seller.products') }}" class="{{ request()->routeIs('seller.products') ? 'active-link' : '' }}">Products</a>
    <a href="{{ route('seller.orders') }}" class="{{ request()->routeIs('seller.orders') ? 'active-link' : '' }}">Orders</a>
    <a href="{{ route('seller.inventory') }}" class="{{ request()->routeIs('seller.inventory') ? 'active-link' : '' }}">Inventory</a>
    <a href="{{ route('seller.reports') }}" class="{{ request()->routeIs('seller.reports') ? 'active-link' : '' }}">Reports</a>
</div>
@endsection
@section('nav-icons')
<a href="{{ route('seller.chat') }}"><i class="fas fa-comment-dots"></i></a>
<a href="{{ route('seller.account') }}"><i class="far fa-user"></i></a>
@endsection


@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-user"></i>
        <div>
            <h1></h1>
            <p></p>
        </div>
    </div>
</div>
<div class="page-content">
    <div class="placeholder-card">
        <i class="fas fa-user"></i>
        <h2>My Account</h2>
        <p>Manage your seller profile.</p>
        <a class="market-button inline-button" href="{{ route('addresses.index') }}">Manage saved addresses</a>
    </div>
</div>
@endsection
