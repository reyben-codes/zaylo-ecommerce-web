@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Dashboard')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Welcome back, {{ Str::before($user->name, ' ') ?: $user->name }}</h1>
            <p>Riders apply to {{ $provider->name }} when they register. Approved riders can sign in and accept deliveries.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('logistics.riders') }}" class="seller-button"><i class="fas fa-user-check" aria-hidden="true"></i> Review applications</a>
        </div>
    </section>

    <section class="seller-stat-grid" aria-label="Rider summary">
        <a href="{{ route('logistics.riders', ['status' => 'pending']) }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Applications</span>
            <strong class="seller-stat__value">{{ $counts['pending'] }}</strong>
            <span class="seller-stat__note{{ $counts['pending'] ? ' seller-stat__note--warning' : '' }}">{{ $counts['pending'] ? 'Waiting for your review' : 'All caught up' }}</span>
        </a>
        <a href="{{ route('logistics.riders', ['status' => 'approved']) }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-motorcycle" aria-hidden="true"></i> Active riders</span>
            <strong class="seller-stat__value">{{ $counts['approved'] }}</strong>
            <span class="seller-stat__note">Can accept deliveries</span>
        </a>
        <a href="{{ route('logistics.riders', ['status' => 'suspended']) }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-ban" aria-hidden="true"></i> Suspended</span>
            <strong class="seller-stat__value">{{ $counts['suspended'] }}</strong>
            <span class="seller-stat__note">Blocked from signing in</span>
        </a>
        <a href="{{ route('logistics.riders', ['status' => 'rejected']) }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-circle-xmark" aria-hidden="true"></i> Rejected</span>
            <strong class="seller-stat__value">{{ $counts['rejected'] }}</strong>
            <span class="seller-stat__note">Applications you declined</span>
        </a>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div>
                <h2>Oldest applications</h2>
                <p>Approve riders whose details match their documents.</p>
            </div>
            <a href="{{ route('logistics.riders') }}" class="seller-link">View all <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </header>
        @if($recentApplications->isEmpty())
            <div class="seller-empty">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <strong>No applications waiting</strong>
                <span>Riders choose {{ $provider->name }} when they sign up at "Become a rider".</span>
            </div>
        @else
            <ul class="workspace-list">
                @foreach($recentApplications as $rider)
                    <li class="workspace-list__item">
                        <div class="workspace-list__main">
                            <strong>{{ $rider->user->name }}</strong>
                            <span>{{ $rider->vehicle_type }}{{ $rider->plate_no ? ' · '.$rider->plate_no : '' }} · applied {{ $rider->created_at?->diffForHumans() }}</span>
                        </div>
                        @include('logistics.partials.rider-actions', ['rider' => $rider])
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
