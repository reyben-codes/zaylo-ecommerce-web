@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Incoming')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Incoming</h1>
            <p>Parcels collected from sellers and on their way to the hub. Receive each one when it arrives.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('logistics.scan') }}" class="seller-button"><i class="fas fa-barcode" aria-hidden="true"></i> Scan a parcel</a>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div><h2>On the way to the hub</h2><p>{{ $shipments->total() }} {{ Str::plural('parcel', $shipments->total()) }}, oldest first.</p></div>
        </header>
        @include('logistics.operations.partials.search', ['action' => route('logistics.incoming'), 'search' => $search])
        @include('logistics.operations.partials.parcel-table', [
            'shipments' => $shipments,
            'empty' => ['icon' => 'fa-truck-ramp-box', 'title' => $search !== '' ? 'No matching parcels' : 'Nothing on the way', 'text' => 'Parcels appear here after they are picked up from the seller.'],
        ])
    </section>
@endsection
