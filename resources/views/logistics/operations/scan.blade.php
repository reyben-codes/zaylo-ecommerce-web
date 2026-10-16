@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Scan parcel')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Scan parcel</h1>
            <p>Scan the label with a barcode scanner, or type the tracking number, to see where the parcel is and move it to its next step.</p>
        </div>
    </section>

    <datalist id="sort-areas">
        @foreach($knownAreas as $known)
            <option value="{{ $known }}"></option>
        @endforeach
    </datalist>

    <section class="seller-panel">
        <form method="GET" action="{{ route('logistics.scan') }}" class="parcel-scan" role="search">
            <label for="tracking" class="seller-field__label">Tracking number</label>
            <div class="parcel-scan__row">
                <i class="fas fa-barcode" aria-hidden="true"></i>
                <input type="search" id="tracking" name="tracking" value="{{ $tracking }}" placeholder="e.g. TRK-OZIMZ8VNRMDW" autocomplete="off" autofocus required maxlength="100">
                <button type="submit" class="seller-button"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Find parcel</button>
            </div>
        </form>

        @if($tracking !== '' && ! $shipment)
            <div class="seller-empty">
                <i class="fas fa-circle-question" aria-hidden="true"></i>
                <strong>No parcel with tracking number "{{ $tracking }}"</strong>
                <span>Check the label and scan again. Tracking numbers look like TRK-XXXXXXXXXXXX.</span>
            </div>
        @elseif($shipment)
            @php $parcel = \App\Support\ParcelView::for($shipment); @endphp
            <div class="parcel-result">
                <div class="parcel-result__head">
                    <div>
                        <span class="seller-cell-note">Parcel</span>
                        <strong class="parcel-tracking">{{ $parcel['tracking'] }}</strong>
                    </div>
                    <span class="parcel-stage parcel-stage--{{ $parcel['stage'] }}">{{ $parcel['label'] }}</span>
                </div>
                <dl class="workspace-detail-list parcel-result__details">
                    <div><dt>Order</dt><dd>{{ $parcel['order'] }}</dd></div>
                    <div><dt>Seller</dt><dd>{{ $parcel['seller'] }}</dd></div>
                    <div><dt>Deliver to</dt><dd>{{ $parcel['recipient'] }}{{ $parcel['phone'] ? ' · '.$parcel['phone'] : '' }}</dd></div>
                    <div><dt>Address</dt><dd>{{ $parcel['destination'] }}</dd></div>
                    @if($parcel['pickup_on'] && $parcel['stage'] === 'confirmed')
                        <div><dt>Pickup date</dt><dd>{{ $parcel['pickup_on']->format('D, M j, Y') }}</dd></div>
                    @endif
                    @if($parcel['area'])
                        <div><dt>Delivery area</dt><dd>{{ $parcel['area'] }}</dd></div>
                    @endif
                    <div><dt>Rider</dt><dd>{{ $parcel['rider'] ?? 'Not assigned' }}</dd></div>
                    <div><dt>Last update</dt><dd>{{ $parcel['updated']?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
                </dl>
                <div class="parcel-result__actions">
                    <p class="seller-cell-note">Next step</p>
                    @include('logistics.operations.partials.parcel-actions', ['shipment' => $shipment, 'riders' => $riders])
                </div>
            </div>
        @else
            <div class="seller-empty">
                <i class="fas fa-barcode" aria-hidden="true"></i>
                <strong>Ready to scan</strong>
                <span>The parcel's details and its next step appear here.</span>
            </div>
        @endif
    </section>
@endsection
