@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Deliveries')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    @php
        $stages = \App\Services\ShipmentWorkflow::STAGES;
        $all = array_sum($counts);
    @endphp
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Deliveries</h1>
            <p>Every parcel and where it is now. Move a parcel on, or mark a failed attempt and retry.</p>
        </div>
    </section>

    <section class="seller-panel">
        <nav class="workspace-tabs" aria-label="Filter parcels by step">
            <a href="{{ route('logistics.deliveries', array_filter(['search' => $search])) }}" class="workspace-tab{{ $stage === null ? ' is-active' : '' }}" @if($stage === null) aria-current="true" @endif>All <span>{{ $all }}</span></a>
            @foreach($stages as $key => $label)
                @continue($counts[$key] === 0 && $stage !== $key)
                <a href="{{ route('logistics.deliveries', array_filter(['stage' => $key, 'search' => $search])) }}" class="workspace-tab{{ $stage === $key ? ' is-active' : '' }}" @if($stage === $key) aria-current="true" @endif>
                    {{ $label }} <span>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('logistics.deliveries') }}" class="seller-filter-bar" role="search">
            @if($stage)<input type="hidden" name="stage" value="{{ $stage }}">@endif
            <div class="seller-field seller-field--search">
                <label class="seller-field__label" for="parcel-search">Search</label>
                <input type="search" id="parcel-search" name="search" value="{{ $search }}" placeholder="Tracking or order number" maxlength="100">
            </div>
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Search</button>
            @if($search !== '')
                <a href="{{ route('logistics.deliveries', array_filter(['stage' => $stage])) }}" class="seller-button seller-button--small seller-button--secondary">Clear</a>
            @endif
        </form>
        @include('logistics.operations.partials.parcel-table', [
            'shipments' => $shipments,
            'empty' => ['icon' => 'fa-route', 'title' => 'No parcels match', 'text' => 'Try another step or search.'],
        ])
    </section>
@endsection
