@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Monitoring')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Delivery monitoring</h1>
            <p>Who is out on the road, what they carry, and parcels that need a follow-up.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('logistics.monitoring') }}" class="seller-button seller-button--secondary"><i class="fas fa-rotate" aria-hidden="true"></i> Refresh</a>
        </div>
    </section>

    <section class="seller-stat-grid" aria-label="Today">
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-motorcycle" aria-hidden="true"></i> On the road</span>
            <strong class="seller-stat__value">{{ $onTheRoad->count() }}</strong>
            <span class="seller-stat__note">Assigned or out for delivery</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-circle-check" aria-hidden="true"></i> Delivered today</span>
            <strong class="seller-stat__value">{{ $deliveredToday }}</strong>
            <span class="seller-stat__note">Since midnight</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-users" aria-hidden="true"></i> Riders working</span>
            <strong class="seller-stat__value">{{ $workload->filter(fn ($row) => $row['assigned'] + $row['out'])->count() }}</strong>
            <span class="seller-stat__note">of {{ $workload->count() }} approved</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Need attention</span>
            <strong class="seller-stat__value">{{ $attention->count() }}</strong>
            <span class="seller-stat__note{{ $attention->isNotEmpty() ? ' seller-stat__note--danger' : '' }}">Failed, or no update for {{ $staleHours }}h</span>
        </div>
    </section>

    @include('logistics.operations.partials.workload', ['workload' => $workload, 'title' => 'Riders', 'note' => 'Parcels each rider has right now.'])

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div><h2>Need attention</h2><p>Failed deliveries, and parcels with no update for {{ $staleHours }} hours.</p></div>
        </header>
        @include('logistics.operations.partials.parcel-table', [
            'shipments' => $attention,
            'empty' => ['icon' => 'fa-face-smile', 'title' => 'All on track', 'text' => 'Nothing is stuck or failed right now.'],
        ])
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div><h2>On the road</h2><p>Parcels with riders, longest without an update first.</p></div>
        </header>
        @include('logistics.operations.partials.parcel-table', [
            'shipments' => $onTheRoad,
            'empty' => ['icon' => 'fa-motorcycle', 'title' => 'No parcels on the road', 'text' => 'Parcels show here once a rider is assigned.'],
        ])
    </section>
@endsection
