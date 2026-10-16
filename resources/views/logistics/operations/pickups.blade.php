@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Pickups')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Pickups</h1>
            <p>{{ $hub ? 'Sellers request a pickup when they mark an order "Ready to ship". Confirm a pickup date or decline with a reason; the seller is told in Messages.' : 'Parcels sellers marked "Ready to ship". Collect them, then mark each one picked up.' }}</p>
        </div>
    </section>

    <section class="seller-panel">
        @if($hub)
            <nav class="workspace-tabs" aria-label="Pickups">
                @foreach(['requests' => 'Requests', 'scheduled' => 'Scheduled'] as $key => $label)
                    <a href="{{ route('logistics.pickups', array_filter(['tab' => $key === 'scheduled' ? 'scheduled' : null, 'search' => $search])) }}" class="workspace-tab{{ $tab === $key ? ' is-active' : '' }}" @if($tab === $key) aria-current="true" @endif>
                        {{ $label }} <span>{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
        <header class="seller-panel__header">
            <div>
                <h2>{{ $tab === 'scheduled' ? 'Confirmed pickups' : ($hub ? 'Waiting for confirmation' : 'Waiting for pickup') }}</h2>
                <p>{{ $shipments->total() }} {{ Str::plural('parcel', $shipments->total()) }}, {{ $tab === 'scheduled' ? 'by pickup date' : 'oldest first' }}.</p>
            </div>
        </header>
        @error('pickup_date')<p class="seller-field__error parcel-report-error" role="alert">{{ $message }}</p>@enderror
        @error('reason')<p class="seller-field__error parcel-report-error" role="alert">{{ $message }}</p>@enderror
        @include('logistics.operations.partials.search', ['action' => route('logistics.pickups'), 'search' => $search, 'keep' => $tab === 'scheduled' ? ['tab' => 'scheduled'] : []])
        @include('logistics.operations.partials.parcel-table', [
            'shipments' => $shipments,
            'empty' => $tab === 'scheduled'
                ? ['icon' => 'fa-calendar-check', 'title' => 'No pickups scheduled', 'text' => 'Confirmed pickups wait here until you collect them.']
                : ['icon' => 'fa-box-open', 'title' => $search !== '' ? 'No matching parcels' : 'No pickup requests', 'text' => 'Requests appear here when a seller marks an order "Ready to ship".'],
        ])
    </section>
@endsection
