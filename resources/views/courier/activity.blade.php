@extends('layouts.app')

@section('title', 'ZAYLO · Activity Log')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/activity-log.css') }}?v={{ filemtime(public_path('css/activity-log.css')) }}">
@endpush

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('courier.dashboard') }}" class="{{ request()->routeIs('courier.dashboard') ? 'active-link' : '' }}">Dashboard</a>
    <a href="{{ route('courier.deliveries') }}" class="{{ request()->routeIs('courier.deliveries') ? 'active-link' : '' }}">Deliveries</a>
    <a href="{{ route('courier.earnings') }}" class="{{ request()->routeIs('courier.earnings') ? 'active-link' : '' }}">Earnings</a>
    <a href="{{ route('courier.history') }}" class="{{ request()->routeIs('courier.history') ? 'active-link' : '' }}">History</a>
    <a href="{{ route('courier.activity') }}" class="{{ request()->routeIs('courier.activity') ? 'active-link' : '' }}">Activity</a>
</div>
@endsection
@section('nav-icons')
<a href="{{ route('courier.chat') }}"><i class="fas fa-comment-dots"></i></a>
<a href="{{ route('courier.account') }}"><i class="far fa-user"></i></a>
@endsection

@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-clock-rotate-left"></i>
        <div>
            <h1>Activity log</h1>
            <p>Your sign-ins and every delivery you claimed or updated, newest first.</p>
        </div>
    </div>
</div>
<div class="page-content">
    <form method="GET" action="{{ route('courier.activity') }}" class="filter-bar activity-filter" role="search">
        <label>
            <span>Search</span>
            <input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="Action or parcel">
        </label>
        <label>
            <span>Type</span>
            <select name="category">
                <option value="">All activity</option>
                @foreach($categories as $value => $label)
                    <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>From</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" max="{{ now('Asia/Manila')->toDateString() }}">
        </label>
        <label>
            <span>To</span>
            <input type="date" name="to" value="{{ $filters['to'] }}" max="{{ now('Asia/Manila')->toDateString() }}">
        </label>
        <div class="activity-filter__actions">
            <button type="submit" class="market-button inline-button">Apply</button>
            @if(array_filter($filters))
                <a href="{{ route('courier.activity') }}" class="activity-filter__reset">Reset</a>
            @endif
        </div>
    </form>
    @if($errors->any())
        <p class="activity-log__error" role="alert">{{ $errors->first() }}</p>
    @endif

    <section class="activity-card" aria-label="Activity">
        @include('partials.activity-log.feed', [
            'logs' => $logs,
            'emptyText' => array_filter($filters) ? 'No activity matches these filters.' : 'No activity yet.',
        ])
    </section>

    @if($logs->hasPages())
        <div class="activity-pagination">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
