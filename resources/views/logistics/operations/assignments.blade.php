@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Assign riders')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Assign riders</h1>
            <p>{{ $hub ? 'Give a whole delivery area to one rider, or assign parcel by parcel.' : 'Parcels at the hub waiting for a rider.' }} {{ $riders->count() }} {{ Str::plural('rider', $riders->count()) }} available right now.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('logistics.monitoring') }}" class="seller-button seller-button--secondary"><i class="fas fa-satellite-dish" aria-hidden="true"></i> Monitoring</a>
        </div>
    </section>

    @if($hub)
        <section class="seller-panel">
            <header class="seller-panel__header">
                <div><h2>By area</h2><p>Sorted parcels waiting for a rider in each delivery area.</p></div>
                @if($area !== '')
                    <a href="{{ route('logistics.assignments') }}" class="workspace-link">Show all areas</a>
                @endif
            </header>
            @error('area')<p class="seller-field__error parcel-report-error" role="alert">{{ $message }}</p>@enderror
            @if($bins->isEmpty())
                <p class="parcel-empty-inline">Nothing to assign. Parcels appear here after they are <a href="{{ route('logistics.sorting') }}" class="workspace-link">sorted</a>.</p>
            @else
                <div class="parcel-bins">
                    @foreach($bins as $bin)
                        <div class="parcel-bin{{ $area === $bin['area'] ? ' is-active' : '' }}">
                            <div class="parcel-bin__head">
                                <a href="{{ route('logistics.assignments', ['area' => $bin['area']]) }}"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $bin['area'] }}</a>
                                <span class="parcel-bin__count">{{ $bin['total'] }} {{ Str::plural('parcel', $bin['total']) }}</span>
                            </div>
                            @if($riders->isNotEmpty())
                                <form method="POST" action="{{ route('logistics.areas.assign') }}" class="parcel-inline-form">
                                    @csrf
                                    <input type="hidden" name="area" value="{{ $bin['area'] }}">
                                    <label class="sr-only" for="area-rider-{{ $loop->index }}">Rider for {{ $bin['area'] }}</label>
                                    <select id="area-rider-{{ $loop->index }}" name="rider_id" required>
                                        <option value="">Choose rider</option>
                                        @foreach($riders as $rider)
                                            <option value="{{ $rider->id }}">{{ $rider->user?->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="seller-button seller-button--small">Assign all</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div><h2>{{ $area !== '' ? 'Parcels for '.$area : 'Waiting for a rider' }}</h2><p>{{ $shipments->total() }} {{ Str::plural('parcel', $shipments->total()) }}, oldest first.</p></div>
        </header>
        @unless($hub)
            <form method="GET" action="{{ route('logistics.assignments') }}" class="seller-filter-bar">
                <div class="seller-field seller-field--search">
                    <label class="seller-field__label" for="area">Delivery area</label>
                    <input type="search" id="area" name="area" value="{{ $area }}" placeholder="City, province or barangay" maxlength="100">
                </div>
                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-location-dot" aria-hidden="true"></i> Filter</button>
                @if($area !== '')
                    <a href="{{ route('logistics.assignments') }}" class="seller-button seller-button--small seller-button--secondary">Clear</a>
                @endif
            </form>
        @endunless
        @error('rider_id')<p class="seller-field__error parcel-report-error" role="alert">{{ $message }}</p>@enderror
        @include('logistics.operations.partials.parcel-table', [
            'shipments' => $shipments,
            'riders' => $riders,
            'empty' => ['icon' => 'fa-check-double', 'title' => $area !== '' ? 'No parcels waiting in this area' : 'Every parcel has a rider', 'text' => $hub ? 'Parcels appear here after they are sorted.' : 'Parcels appear here after they are received at the hub.'],
        ])
    </section>

    @include('logistics.operations.partials.workload', ['workload' => $workload, 'title' => 'Rider workload', 'note' => 'Current load per rider, to spread deliveries evenly.'])
@endsection
