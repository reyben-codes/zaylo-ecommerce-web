@extends('layouts.app')

@section('title', 'ZAYLO · Pickup History')

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('courier.dashboard') }}" class="{{ request()->routeIs('courier.dashboard') ? 'active-link' : '' }}">Dashboard</a>
    <a href="{{ route('courier.deliveries') }}" class="{{ request()->routeIs('courier.deliveries') ? 'active-link' : '' }}">Pickups & Hub Transit</a>
    <a href="{{ route('courier.earnings') }}" class="{{ request()->routeIs('courier.earnings') ? 'active-link' : '' }}">Earnings</a>
    <a href="{{ route('courier.history') }}" class="{{ request()->routeIs('courier.history') ? 'active-link' : '' }}">History</a>
    <a href="{{ route('courier.activity') }}" class="{{ request()->routeIs('courier.activity') ? 'active-link' : '' }}">Activity</a>
</div>
@endsection

@section('nav-icons')
<a href="{{ route('courier.chat') }}"><i class="fas fa-comment-dots"></i></a>
<a href="{{ route('courier.account') }}"><i class="far fa-user"></i></a>
@endsection

@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-history"></i>
        <div>
            <h1>Pickup History</h1>
            <p>Review parcels you collected from sellers and handed over to the sorting hub.</p>
        </div>
    </div>
</div>
<div class="page-content">
    @if($shipments->isEmpty())
        <div class="placeholder-card">
            <i class="fas fa-history"></i>
            <h2>No Completed Pickups Yet</h2>
            <p>Parcels handed over to the hub will appear here.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Tracking</th>
                        <th>Order</th>
                        <th>Seller</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shipments as $shipment)
                        @php $parcel = \App\Support\ParcelView::for($shipment); @endphp
                        <tr>
                            <td>{{ $shipment->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td>{{ $parcel['tracking'] }}</td>
                            <td>{{ $parcel['order'] }}</td>
                            <td>{{ $parcel['seller'] }}</td>
                            <td>{{ $parcel['label'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $shipments->links() }}
    @endif
</div>
@endsection
