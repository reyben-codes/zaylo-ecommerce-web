@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Sorting')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Sorting</h1>
            <p>Sort each parcel received at the hub into its delivery area. The area is filled in from the address; change it if needed.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('logistics.assignments') }}" class="seller-button seller-button--secondary"><i class="fas fa-people-arrows" aria-hidden="true"></i> Assign riders</a>
        </div>
    </section>

    <datalist id="sort-areas">
        @foreach($knownAreas as $known)
            <option value="{{ $known }}"></option>
        @endforeach
    </datalist>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div><h2>To sort</h2><p>{{ $shipments->total() }} {{ Str::plural('parcel', $shipments->total()) }} at the hub, oldest first.</p></div>
        </header>
        @error('area')<p class="seller-field__error parcel-report-error" role="alert">{{ $message }}</p>@enderror
        @include('logistics.operations.partials.search', ['action' => route('logistics.sorting'), 'search' => $search])
        @include('logistics.operations.partials.parcel-table', [
            'shipments' => $shipments,
            'empty' => ['icon' => 'fa-layer-group', 'title' => $search !== '' ? 'No matching parcels' : 'Nothing to sort', 'text' => 'Parcels appear here after you receive them at the hub.'],
        ])
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div><h2>Sorted, waiting for a rider</h2><p>Parcels per delivery area. Open an area to assign it.</p></div>
        </header>
        @if($bins->isEmpty())
            <p class="parcel-empty-inline">No sorted parcels are waiting for a rider.</p>
        @else
            @php $largest = max(1, $bins->max('total')); @endphp
            <ul class="parcel-breakdown">
                @foreach($bins as $bin)
                    <li>
                        <a href="{{ route('logistics.assignments', ['area' => $bin['area']]) }}" class="workspace-link">{{ $bin['area'] }}</a>
                        <span class="parcel-breakdown__bar" aria-hidden="true"><span style="--share: {{ round($bin['total'] / $largest * 100) }}%"></span></span>
                        <strong>{{ $bin['total'] }}</strong>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
