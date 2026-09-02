@extends('layouts.app')

@section('title', 'ZAYLO · Shopping Cart')

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active-link' : '' }}">Home</a>
    <a href="{{ route('buyer.products') }}" class="{{ request()->routeIs('buyer.products') ? 'active-link' : '' }}">Clothing</a>
    <a href="{{ route('buyer.products') }}" class="{{ request()->routeIs('buyer.products') ? 'active-link' : '' }}">Bags</a>
    <a href="{{ route('buyer.products') }}" class="{{ request()->routeIs('buyer.products') ? 'active-link' : '' }}">Shoes</a>
    <a href="{{ route('buyer.products') }}" class="{{ request()->routeIs('buyer.products') ? 'active-link' : '' }}">Accessories</a>
</div>
@endsection
@section('nav-icons')
<a href="{{ route('buyer.wishlist') }}" style="position:relative;"><i class="far fa-heart"></i></a>
<a href="{{ route('buyer.cart') }}" style="position:relative;"><i class="fas fa-shopping-bag"></i></a>
<a href="{{ route('buyer.account') }}"><i class="far fa-user"></i></a>
@endsection


@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-shopping-bag"></i>
        <div>
            <h1></h1>
            <p></p>
        </div>
    </div>
</div>
<div class="page-content">
    <div class="placeholder-card">
        <i class="fas fa-shopping-bag"></i>
        <h2>Shopping Cart</h2>
        <p>Review items in your cart before checkout.</p>
    </div>
</div>
@endsection
