@extends('layouts.app')

@section('title', 'Pickups & Hub Transit · ZAYLO')

@section('nav-links')
    <nav class="nav-links">
        <a href="{{ route('courier.dashboard') }}">Dashboard</a>
        <a class="active-link" href="{{ route('courier.deliveries') }}">Pickups & Hub Transit</a>
        <a href="{{ route('courier.earnings') }}">Earnings</a>
        <a href="{{ route('courier.activity') }}">Activity</a>
    </nav>
@endsection

@section('content')
    <div class="page-hero">
        <div class="page-hero-inner">
            <i class="fas fa-truck-pickup" aria-hidden="true"></i>
            <div>
                <h1>First-Mile Pickups</h1>
                <p>Pick up prepared packages from sellers and hand them over to your logistics partner's sorting hub.</p>
            </div>
        </div>
    </div>

    <div class="page-content">
        @include('partials.print-header', ['title' => 'Courier Pickups'])

        <div class="page-actions">
            <button type="button" class="market-button" data-print hidden><i class="fas fa-print" aria-hidden="true"></i> Print</button>
        </div>

        <h2>Available seller pickups</h2>
        <div class="delivery-grid">
            @forelse($available as $shipment)
                @php $parcel = \App\Support\ParcelView::for($shipment); @endphp
                <article class="delivery-card">
                    <span class="status-pill">Ready for pickup</span>
                    <h3>{{ $parcel['tracking'] }}</h3>
                    <p><strong>Seller:</strong> {{ $parcel['seller'] }}</p>
                    <p><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $parcel['pickup_address'] }}</p>
                    @if($parcel['seller_phone'])
                        <p><i class="fas fa-phone" aria-hidden="true"></i> {{ $parcel['seller_phone'] }}</p>
                    @endif
                    @if($parcel['pickup_on'])
                        <p class="seller-cell-note"><i class="fas fa-calendar" aria-hidden="true"></i> Scheduled: {{ $parcel['pickup_on']->format('M j, Y') }}</p>
                    @endif
                    <form method="POST" action="{{ route('courier.deliveries.claim', $shipment) }}">
                        @csrf
                        <button type="submit" class="market-button">Accept pickup task</button>
                    </form>
                </article>
            @empty
                <p class="empty-note">No seller pickups are waiting right now.</p>
            @endforelse
        </div>

        <h2 class="section-heading">My assigned tasks</h2>
        <div class="delivery-grid">
            @forelse($assigned as $shipment)
                @php $parcel = \App\Support\ParcelView::for($shipment); @endphp
                <article class="delivery-card">
                    <span class="status-pill">{{ $shipment->status === 'picked_up' ? 'In transit to hub' : 'To pick up' }}</span>
                    <h3>{{ $parcel['tracking'] }}</h3>
                    <span class="seller-cell-note">Order {{ $parcel['order'] }}</span>
                    <p><strong>Seller:</strong> {{ $parcel['seller'] }}</p>
                    <p><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $parcel['pickup_address'] }}</p>
                    @if($parcel['seller_phone'])
                        <p><i class="fas fa-phone" aria-hidden="true"></i> {{ $parcel['seller_phone'] }}</p>
                    @endif

                    @if(\App\Support\Waybill::available($shipment))
                        <a class="text-button" href="{{ route('courier.deliveries.waybill.print', $shipment) }}" target="_blank" rel="noopener" aria-label="Print waybill for parcel {{ $parcel['tracking'] }} (opens in a new tab)">
                            <i class="fas fa-print" aria-hidden="true"></i> Print waybill
                        </a>
                    @endif

                    @if(in_array($shipment->status, ['ready', 'confirmed'], true))
                        <form method="POST" action="{{ route('courier.deliveries.update', $shipment) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="picked_up">
                            <label for="tracking-{{ $shipment->id }}">Scan / verify waybill code:</label>
                            <input type="text" id="tracking-{{ $shipment->id }}" name="tracking" class="market-input" placeholder="Scan or enter waybill code" required autocomplete="off">
                            <button type="submit" class="market-button"><i class="fas fa-box-check" aria-hidden="true"></i> Confirm pickup from seller</button>
                        </form>
                    @elseif($shipment->status === 'picked_up')
                        <form method="POST" action="{{ route('courier.deliveries.update', $shipment) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="in_transit">
                            <p class="seller-cell-note"><i class="fas fa-truck" aria-hidden="true"></i> Parcel collected from seller. Bring it to the sorting center.</p>
                            <button type="submit" class="market-button"><i class="fas fa-warehouse" aria-hidden="true"></i> Hand over to hub</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="empty-note">You have no active pickup tasks.</p>
            @endforelse
        </div>
    </div>
@endsection
