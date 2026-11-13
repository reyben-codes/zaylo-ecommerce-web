@extends('layouts.seller')

@section('auto-refresh', 'on')
@section('title', 'Pickup Management')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-pickups.css') }}?v={{ filemtime(public_path('css/seller-pickups.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/smooth-disclosure.js') }}" defer></script>
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Pickup Management & Hub Tracking</h1>
            <p>Schedule pickups, track assigned pickup riders, confirm doorstep handovers, and monitor hub arrivals.</p>
        </div>
    </section>

    @if (session('status'))
        <div class="seller-alert is-success" role="status">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    <div class="pickups-container">
        {{-- Section 1: Schedule Pickup --}}
        <section class="pickup-section-card" id="schedule-pickup-section">
            <div class="pickup-section-header">
                <h2 class="pickup-section-title">
                    <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                    Schedule Pickup
                    <span class="pickup-badge pickup-badge--info">{{ $readyOrders->count() }} ready</span>
                </h2>
            </div>

            @if ($readyOrders->isEmpty())
                <div class="pickup-empty-state">
                    <i class="fas fa-box-open" aria-hidden="true"></i>
                    <p>No orders are currently waiting to be scheduled for pickup.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="pickup-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Items</th>
                                <th>Destination</th>
                                <th>Logistics Partner</th>
                                <th>Preferred Pickup Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($readyOrders as $orderItem)
                                @php
                                    $order = $legacy ? $orderItem : $orderItem->order;
                                    $itemsCount = $orderItem->items?->count() ?? 0;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>#{{ $order->order_number ?? $order->id }}</strong>
                                        <div class="pickup-rider-sub">{{ $order->created_at?->format('M d, Y') }}</div>
                                    </td>
                                    <td>{{ $itemsCount }} {{ \Illuminate\Support\Str::plural('item', $itemsCount) }}</td>
                                    <td>{{ $order->shipping_city ?? $order->delivery_address ?? 'Standard Delivery' }}</td>
                                    <form action="{{ route('seller.pickups.schedule') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                                        <td>
                                            @if ($partners->isNotEmpty())
                                                <select name="logistics_provider_id" class="pickup-select" required>
                                                    @foreach ($partners as $partner)
                                                        <option value="{{ $partner->id }}" {{ $suggestedPartner == $partner->id ? 'selected' : '' }}>
                                                            {{ $partner->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="pickup-badge pickup-badge--warning">Standard Network</span>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="date" name="pickup_date" class="pickup-input"
                                                   min="{{ now()->format('Y-m-d') }}"
                                                   value="{{ now()->addDay()->format('Y-m-d') }}" required>
                                        </td>
                                        <td>
                                            <button type="submit" class="pickup-btn pickup-btn--primary">
                                                <i class="fas fa-check" aria-hidden="true"></i> Schedule
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Section 2: Assigned Pickups --}}
        <section class="pickup-section-card" id="assigned-pickups-section">
            <div class="pickup-section-header">
                <h2 class="pickup-section-title">
                    <i class="fas fa-motorcycle" aria-hidden="true"></i>
                    Assigned Pickups
                    <span class="pickup-badge pickup-badge--purple">{{ $scheduledShipments->count() }} active</span>
                </h2>
            </div>

            @if ($scheduledShipments->isEmpty())
                <div class="pickup-empty-state">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <p>No pickups currently assigned to riders.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="pickup-table">
                        <thead>
                            <tr>
                                <th>Tracking / Order</th>
                                <th>Logistics Partner</th>
                                <th>Assigned Pickup Rider</th>
                                <th>Scheduled Date</th>
                                <th>Status</th>
                                <th>Waybill</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($scheduledShipments as $shipment)
                                @php
                                    $order = $shipment->order ?? ($shipment->sellerOrder?->order ?? null);
                                    $rider = $shipment->pickupCourier?->rider;
                                    $riderUser = $shipment->pickupCourier;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $shipment->tracking_number ?? 'Pending' }}</strong>
                                        <div class="pickup-rider-sub">Order #{{ $order?->order_number ?? $order?->id }}</div>
                                    </td>
                                    <td>{{ $shipment->provider?->name ?? 'Standard Logistics' }}</td>
                                    <td>
                                        @if ($riderUser)
                                            <div class="pickup-rider-card">
                                                <div class="pickup-rider-avatar">
                                                    {{ strtoupper(substr($riderUser->name, 0, 1)) }}
                                                </div>
                                                <div class="pickup-rider-details">
                                                    <span class="pickup-rider-name">{{ $riderUser->name }}</span>
                                                    <span class="pickup-rider-sub">
                                                        <i class="fas fa-phone-alt" aria-hidden="true"></i> {{ $riderUser->phone_number ?? 'No phone' }}
                                                        @if ($rider?->plate_no)
                                                            | <i class="fas fa-id-card" aria-hidden="true"></i> {{ $rider->plate_no }} ({{ $rider->vehicle_type ?? 'Bike' }})
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="pickup-badge pickup-badge--warning">Awaiting Rider Assignment</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $shipment->pickup_scheduled_for ? \Carbon\Carbon::parse($shipment->pickup_scheduled_for)->format('M d, Y') : 'Today' }}
                                    </td>
                                    <td>
                                        <span class="pickup-badge pickup-badge--info">
                                            {{ ucfirst(\App\Services\ShipmentWorkflow::stageOf($shipment)) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($order)
                                            <a href="{{ route('seller.orders.waybill', $order) }}" target="_blank" class="pickup-btn pickup-btn--secondary">
                                                <i class="fas fa-file-invoice" aria-hidden="true"></i> Print Waybill
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Section 3: Doorstep Handover Confirmation --}}
        <section class="pickup-section-card" id="handover-confirmation-section">
            <div class="pickup-section-header">
                <h2 class="pickup-section-title">
                    <i class="fas fa-handshake" aria-hidden="true"></i>
                    Doorstep Handover Confirmation
                    <span class="pickup-badge pickup-badge--warning">{{ $inTransitShipments->count() }} awaiting</span>
                </h2>
            </div>

            @if ($inTransitShipments->isEmpty())
                <div class="pickup-empty-state">
                    <i class="fas fa-check-double" aria-hidden="true"></i>
                    <p>No parcels currently awaiting doorstep handover confirmation.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="pickup-table">
                        <thead>
                            <tr>
                                <th>Tracking / Order</th>
                                <th>Pickup Rider</th>
                                <th>Rider Scanned At</th>
                                <th>Handover Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inTransitShipments as $shipment)
                                @php
                                    $order = $shipment->order ?? ($shipment->sellerOrder?->order ?? null);
                                    $riderUser = $shipment->pickupCourier;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $shipment->tracking_number }}</strong>
                                        <div class="pickup-rider-sub">Order #{{ $order?->order_number ?? $order?->id }}</div>
                                    </td>
                                    <td>
                                        <span class="pickup-rider-name">{{ $riderUser?->name ?? 'Pickup Rider' }}</span>
                                        <div class="pickup-rider-sub">{{ $riderUser?->phone_number }}</div>
                                    </td>
                                    <td>
                                        {{ $shipment->pickup_scanned_at ? $shipment->pickup_scanned_at->format('M d, Y h:i A') : 'Just now' }}
                                    </td>
                                    <td>
                                        @if ($shipment->handover_confirmed_at)
                                            <span class="pickup-badge pickup-badge--success">
                                                <i class="fas fa-check" aria-hidden="true"></i> Confirmed
                                            </span>
                                        @else
                                            <span class="pickup-badge pickup-badge--warning">
                                                <i class="fas fa-clock" aria-hidden="true"></i> Scanned - Confirm Now
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($order && ! $shipment->handover_confirmed_at)
                                            <form action="{{ route('seller.pickups.confirm-handover', $order) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="pickup-btn pickup-btn--success">
                                                    <i class="fas fa-hand-holding-box" aria-hidden="true"></i> Confirm Handover
                                                </button>
                                            </form>
                                        @else
                                            <span class="pickup-badge pickup-badge--success">Handover Verified</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Section 4: Hub Arrival Tracking --}}
        <section class="pickup-section-card" id="hub-arrival-section">
            <div class="pickup-section-header">
                <h2 class="pickup-section-title">
                    <i class="fas fa-warehouse" aria-hidden="true"></i>
                    Hub Arrival Tracking
                    <span class="pickup-badge pickup-badge--success">{{ $atHubShipments->count() }} received</span>
                </h2>
            </div>

            @if ($atHubShipments->isEmpty())
                <div class="pickup-empty-state">
                    <i class="fas fa-truck-ramp-box" aria-hidden="true"></i>
                    <p>No parcels have reached or been checked into the hub yet.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="pickup-table">
                        <thead>
                            <tr>
                                <th>Tracking / Order</th>
                                <th>Logistics Partner</th>
                                <th>Hub Arrival Time</th>
                                <th>Hub Checklist</th>
                                <th>Current Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($atHubShipments as $shipment)
                                @php
                                    $order = $shipment->order ?? ($shipment->sellerOrder?->order ?? null);
                                    $receipt = $shipment->hub_receipt_checks ?? [];
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $shipment->tracking_number }}</strong>
                                        <div class="pickup-rider-sub">Order #{{ $order?->order_number ?? $order?->id }}</div>
                                    </td>
                                    <td>{{ $shipment->provider?->name ?? 'Logistics Partner' }}</td>
                                    <td>
                                        @if ($shipment->hub_received_at)
                                            {{ $shipment->hub_received_at->format('M d, Y h:i A') }}
                                        @else
                                            <span class="pickup-rider-sub">Processing at Hub</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (! empty($receipt))
                                            <span class="pickup-badge pickup-badge--success">
                                                <i class="fas fa-clipboard-check" aria-hidden="true"></i> 3/3 Verified
                                            </span>
                                        @else
                                            <span class="pickup-badge pickup-badge--info">Received</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="pickup-badge pickup-badge--purple">
                                            {{ ucfirst(\App\Services\ShipmentWorkflow::stageOf($shipment)) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection

