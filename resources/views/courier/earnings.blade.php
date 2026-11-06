@extends('layouts.app')

@section('title', 'ZAYLO · Earnings')

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('courier.dashboard') }}" class="{{ request()->routeIs('courier.dashboard') ? 'active-link' : '' }}">Dashboard</a>
    <a href="{{ route('courier.deliveries') }}" class="{{ request()->routeIs('courier.deliveries') ? 'active-link' : '' }}">Deliveries</a>
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
@php
    $collected = fn ($shipment) => $shipment->order?->payment_method === 'cod' ? (float) $shipment->order->total : 0.0;
    $thisMonth = $shipments->filter(fn ($shipment) => ($shipment->delivered_at ?? $shipment->updated_at)?->timezone('Asia/Manila')->isSameMonth(now('Asia/Manila')));
@endphp
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-money-bill-wave"></i>
        <div>
            <h1>Earnings</h1>
            <p>Your completed deliveries and the cash you collected on delivery.</p>
        </div>
    </div>
</div>
<div class="page-content">
    @include('partials.print-header', ['title' => 'Rider earnings · '.auth()->user()->name])
    <div class="page-actions"><button type="button" class="market-button" data-print hidden><i class="fas fa-print" aria-hidden="true"></i> Print report</button></div>
    <div class="metric-grid">
        <div class="metric-card"><span>Deliveries completed</span><strong>{{ $shipments->count() }}</strong></div>
        <div class="metric-card"><span>This month</span><strong>{{ $thisMonth->count() }}</strong></div>
        <div class="metric-card"><span>Cash collected</span><strong>&#8369;{{ number_format($shipments->sum($collected), 2) }}</strong></div>
        <div class="metric-card"><span>Collected this month</span><strong>&#8369;{{ number_format($thisMonth->sum($collected), 2) }}</strong></div>
    </div>
    <h2 class="section-heading">Completed deliveries</h2>
    @if($shipments->isEmpty())
        <p class="empty-note">No completed deliveries yet.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Delivered</th><th>Tracking</th><th>Order</th><th>Recipient</th><th>Payment</th><th>Collected</th></tr></thead>
                <tbody>
                    @foreach($shipments as $shipment)
                        <tr>
                            <td>{{ ($shipment->delivered_at ?? $shipment->updated_at)?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td>{{ $shipment->tracking_number }}</td>
                            <td>{{ $shipment->order?->order_number }}</td>
                            <td>{{ $shipment->order?->recipient_name }}</td>
                            <td>{{ $shipment->order?->payment_method === 'cod' ? 'Cash on delivery' : 'Paid online' }}</td>
                            <td>&#8369;{{ number_format($collected($shipment), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
