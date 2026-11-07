@extends('layouts.app')
@section('title', 'Courier Dashboard · ZAYLO')
@section('nav-links')
    <nav class="nav-links">
        <a class="active-link" href="{{ route('courier.dashboard') }}">Dashboard</a>
        <a href="{{ route('courier.deliveries') }}">Pickups & Hub Transit</a>
        <a href="{{ route('courier.earnings') }}">Earnings</a>
        <a href="{{ route('courier.activity') }}">Activity</a>
    </nav>
@endsection
@section('content')
    <div class="page-hero">
        <div class="page-hero-inner">
            <i class="fas fa-truck-pickup" aria-hidden="true"></i>
            <div>
                <h1>Welcome, {{ $user->name }}</h1>
                <p>@if($company)Courier for <strong>{{ $company }}</strong>. @endif First-mile pickup fleet: collect parcels from sellers and transport them to the sorting hub.</p>
            </div>
        </div>
    </div>
    <div class="page-content">
        <div class="metric-grid">
            <div class="metric-card">
                <span>Available pickups</span>
                <strong>{{ $availableCount }}</strong>
            </div>
            <div class="metric-card">
                <span>Active tasks</span>
                <strong>{{ $activeCount }}</strong>
            </div>
            <div class="metric-card">
                <span>Completed handovers</span>
                <strong>{{ $completedCount }}</strong>
            </div>
        </div>
        <a class="market-button inline-button" href="{{ route('courier.deliveries') }}">View pickup tasks</a>
    </div>
@endsection
