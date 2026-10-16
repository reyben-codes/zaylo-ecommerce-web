@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    @php
        // Without the sorting-center columns (migration not run yet) parcels go straight from the hub to a rider.
        $hub = \App\Services\ShipmentWorkflow::hasHubSteps();
        $pipeline = [
            ['label' => 'Pickup requests', 'icon' => 'fa-box-open', 'count' => $parcels['ready'], 'route' => route('logistics.pickups'), 'note' => $hub ? 'Confirm or decline' : 'Sellers marked ready to ship'],
            $hub ? ['label' => 'Pickups scheduled', 'icon' => 'fa-calendar-check', 'count' => $parcels['confirmed'], 'route' => route('logistics.pickups', ['tab' => 'scheduled']), 'note' => 'Confirmed, to collect'] : null,
            ['label' => 'Incoming', 'icon' => 'fa-arrow-down', 'count' => $parcels['picked_up'], 'route' => route('logistics.incoming'), 'note' => 'On the way to the hub'],
            $hub ? ['label' => 'To sort', 'icon' => 'fa-layer-group', 'count' => $parcels['in_transit'], 'route' => route('logistics.sorting'), 'note' => 'At the hub'] : null,
            ['label' => 'Waiting for a rider', 'icon' => 'fa-people-arrows', 'count' => $parcels[$hub ? 'sorted' : 'in_transit'], 'route' => route('logistics.assignments'), 'note' => $hub ? 'Sorted by area' : 'At the hub'],
            ['label' => 'With riders', 'icon' => 'fa-motorcycle', 'count' => $parcels['assigned'] + $parcels['out_for_delivery'], 'route' => route('logistics.monitoring'), 'note' => 'Assigned or out for delivery'],
            ['label' => 'Delivered', 'icon' => 'fa-circle-check', 'count' => $parcels['delivered'], 'route' => route('logistics.deliveries', ['stage' => 'delivered']), 'note' => 'Completed deliveries'],
            ['label' => 'Failed', 'icon' => 'fa-triangle-exclamation', 'count' => $parcels['failed'], 'route' => route('logistics.deliveries', ['stage' => 'failed']), 'note' => 'Retry or return', 'warn' => true],
        ];
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Welcome back, {{ Str::before($user->name, ' ') ?: $user->name }}</h1>
            <p>Your sorting center today: collect parcels from sellers, receive them at the hub, and send them out with your riders.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('logistics.scan') }}" class="seller-button"><i class="fas fa-barcode" aria-hidden="true"></i> Scan a parcel</a>
        </div>
    </section>

    <section class="seller-stat-grid parcel-pipeline" aria-label="Parcels at each step">
        @foreach(array_filter($pipeline) as $step)
            <a href="{{ $step['route'] }}" class="seller-stat">
                <span class="seller-stat__label"><i class="fas {{ $step['icon'] }}" aria-hidden="true"></i> {{ $step['label'] }}</span>
                <strong class="seller-stat__value">{{ $step['count'] }}</strong>
                <span class="seller-stat__note{{ ($step['warn'] ?? false) && $step['count'] ? ' seller-stat__note--danger' : '' }}">{{ $step['note'] }}</span>
            </a>
        @endforeach
    </section>

    <div class="seller-panel-grid logistics-dashboard-grid">
        <section class="seller-panel">
            <header class="seller-panel__header">
                <div><h2>Active parcels</h2><p>The latest movement across your sorting center.</p></div>
                <a href="{{ route('logistics.deliveries') }}" class="seller-link">All deliveries <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </header>
            @include('logistics.operations.partials.parcel-table', [
                'shipments' => $recentParcels,
                'empty' => ['icon' => 'fa-circle-check', 'title' => 'No parcels in progress', 'text' => 'Parcels appear here when a seller marks an order "Ready to ship".'],
            ])
        </section>

        <section class="seller-panel">
            <header class="seller-panel__header">
                <div><h2>Riders</h2><p>{{ $availableRiders }} available for new deliveries.</p></div>
                <a href="{{ route('logistics.riders') }}" class="seller-link">Manage <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </header>
            <dl class="seller-panel__body workspace-detail-list">
                <div><dt>Applications to review</dt><dd>{{ $counts['pending'] }}</dd></div>
                <div><dt>Approved riders</dt><dd>{{ $counts['approved'] }}</dd></div>
                <div><dt>Suspended</dt><dd>{{ $counts['suspended'] }}</dd></div>
            </dl>
            @if($recentApplications->isNotEmpty())
                <ul class="workspace-list">
                    @foreach($recentApplications as $rider)
                        <li class="workspace-list__item">
                            <div class="workspace-list__main">
                                <strong>{{ $rider->user->name }}</strong>
                                <span>{{ $rider->vehicle_type }}{{ $rider->plate_no ? ' · '.$rider->plate_no : '' }} · applied {{ $rider->created_at?->diffForHumans() }}</span>
                            </div>
                            <a href="{{ route('logistics.riders', ['status' => 'pending']) }}" class="seller-button seller-button--small seller-button--secondary">Review</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
