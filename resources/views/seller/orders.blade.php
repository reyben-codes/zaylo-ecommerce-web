@extends('layouts.seller')

@section('title', request()->routeIs('seller.handover') ? 'Delivery' : 'Order Fulfillment')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-orders.css') }}?v={{ filemtime(public_path('css/seller-orders.css')) }}">
@endpush

@section('content')
    @php
        $isDelivery = request()->routeIs('seller.handover');
        $tabs = [
            'all' => 'All',
            'to_prepare' => 'To prepare',
            'shipping' => 'Shipping',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
        $nextActions = [
            'placed' => ['status' => 'confirmed', 'label' => 'Confirm order', 'icon' => 'fa-check'],
            'confirmed' => ['status' => 'processing', 'label' => 'Start processing', 'icon' => 'fa-box-open'],
            'processing' => ['status' => 'ready_for_pickup', 'label' => 'Ready to ship', 'icon' => 'fa-box'],
        ];
        $partners = $partners ?? collect();
        $suggestedPartner = $suggestedPartner ?? null;
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>{{ $isDelivery ? 'Delivery' : 'Order fulfillment' }}</h1>
            <p>{{ $isDelivery ? 'Parcels that are packed or on the way to buyers.' : 'Prepare orders and hand them over to your logistics partner.' }}</p>
        </div>
    </section>

    <aside class="seller-notice">
        <i class="fas fa-circle-info" aria-hidden="true"></i>
        <div>
            <strong>Your logistics partner handles delivery</strong>
            <p>When an order is ready to ship, choose the logistics partner, then print the waybill and attach it to the parcel. The partner sets a pickup date, scans the waybill when they collect the parcel, and delivers it with their riders. The buyer sees every update in their order history.</p>
        </div>
    </aside>

    <section class="seller-stat-grid" aria-label="Order summary">
        <article class="seller-stat"><span class="seller-stat__label">All orders</span><strong class="seller-stat__value">{{ $counts['all'] }}</strong></article>
        <article class="seller-stat"><span class="seller-stat__label">To prepare</span><strong class="seller-stat__value">{{ $counts['to_prepare'] }}</strong></article>
        <article class="seller-stat"><span class="seller-stat__label">In delivery</span><strong class="seller-stat__value">{{ $counts['shipping'] }}</strong></article>
        <article class="seller-stat"><span class="seller-stat__label">Delivered</span><strong class="seller-stat__value">{{ $counts['delivered'] }}</strong></article>
    </section>

    <section class="seller-panel">
        <div class="orders-toolbar">
            <nav class="order-tabs" aria-label="Filter seller orders">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('seller.orders', $key === 'all' ? [] : ['status' => $key]) }}"
                       class="order-tab{{ $filter === $key ? ' is-active' : '' }}"
                       @if($filter === $key) aria-current="true" @endif>
                        {{ $label }} <span>{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <label class="order-search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <span class="sr-only">Search visible orders</span>
                <input type="search" placeholder="Search orders" data-search-input="orders">
            </label>
        </div>

        <div class="seller-table-wrap">
            <table class="seller-table orders-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Buyer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Next action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $nextAction = $nextActions[$order->status] ?? null;
                            $itemNames = $order->items->pluck('product_name')->filter();
                            $searchText = str($order->order_number.' '.$order->buyer?->name.' '.$itemNames->join(' ').' '.$order->status)->lower();
                        @endphp
                        <tr data-search-item="orders" data-search-text="{{ $searchText }}">
                            <td>
                                <strong class="order-reference">{{ $order->order_number }}</strong>
                                <span class="seller-cell-note">{{ ($order->placed_at ?? $order->created_at)?->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</span>
                                @if($order->shipment)
                                    <span class="seller-cell-note tracking-number">{{ $order->shipment->tracking_number }}</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $order->buyer?->name ?? 'Buyer' }}</strong>
                                <span class="seller-cell-note">{{ $order->recipient_name }}</span>
                            </td>
                            <td>
                                <strong>{{ $itemNames->first() ?? 'Order items' }}</strong>
                                @if($order->items->count() > 1)
                                    <span class="seller-cell-note">+ {{ $order->items->count() - 1 }} more</span>
                                @endif
                            </td>
                            <td><strong>&#8369;{{ number_format($order->total, 2) }}</strong></td>
                            <td>
                                <span class="order-status order-status--{{ str($order->status)->replace('_', '-') }}">
                                    {{ $statusLabels[$order->status] ?? str($order->status)->replace('_', ' ')->title() }}
                                </span>
                                @include('seller.partials.pickup-status', ['shipment' => $order->shipment])
                            </td>
                            <td>
                                <div class="order-actions">
                                    @if($nextAction)
                                        <form method="POST" action="{{ route('seller.orders.status', $order) }}"
                                              @if($nextAction['status'] === 'completed') data-confirm="Confirm that the buyer received order {{ $order->order_number }}? This marks the payment as paid and completes the order." data-confirm-title="Mark as delivered?" data-confirm-button="Mark delivered" @endif>
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $nextAction['status'] }}">
                                            @if($nextAction['status'] === 'ready_for_pickup' && $partners->count() > 1)
                                                <label class="sr-only" for="partner-{{ $order->id }}">Logistics partner for order {{ $order->order_number }}</label>
                                                <select id="partner-{{ $order->id }}" name="logistics_provider_id" class="order-partner" required>
                                                    @foreach($partners as $partner)
                                                        <option value="{{ $partner->id }}" @selected($partner->id === $suggestedPartner)>{{ $partner->name }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($nextAction['status'] === 'ready_for_pickup' && $partners->count() === 1)
                                                <input type="hidden" name="logistics_provider_id" value="{{ $partners->first()->id }}">
                                            @endif
                                            <button type="submit" class="seller-button seller-button--small"
                                                    @if($nextAction['status'] === 'ready_for_pickup' && $partners->count() === 1) aria-label="Ready to ship: request pickup from {{ $partners->first()->name }}" @endif>
                                                <i class="fas {{ $nextAction['icon'] }}" aria-hidden="true"></i> {{ $nextAction['label'] }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="seller-cell-note">No action needed</span>
                                    @endif

                                    {{-- The rider scans the waybill's barcode or QR code to start the delivery and again to confirm it. --}}
                                    @if(\App\Support\Waybill::available($order->shipment))
                                        <a href="{{ route('seller.orders.waybill.print', $order) }}" class="seller-button seller-button--small seller-button--secondary" target="_blank" rel="noopener"
                                           aria-label="Print waybill for order {{ $order->order_number }} (opens in a new tab)"><i class="fas fa-print" aria-hidden="true"></i> Print waybill</a>
                                    @endif

                                    <details class="order-details">
                                        <summary>Details</summary>
                                        <div class="order-details-card">
                                            <strong>Delivery details</strong>
                                            <p>{{ $order->recipient_name }} · {{ $order->phone }}</p>
                                            <p>{{ $order->shipping_address }}</p>
                                            @if($order->shipment?->picked_up_at)
                                                <p>Shipped: {{ $order->shipment->picked_up_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p>
                                            @endif
                                            @if($order->shipment?->delivered_at)
                                                <p>Delivered: {{ $order->shipment->delivered_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p>
                                            @endif
                                            <strong>Items</strong>
                                            <ul>
                                                @foreach($order->items as $item)
                                                    <li><span>{{ $item->product_name }} × {{ $item->quantity }}</span><span>&#8369;{{ number_format($item->line_total, 2) }}</span></li>
                                                @endforeach
                                            </ul>
                                            <strong>Status history</strong>
                                            <ol>
                                                @forelse($order->statusHistory as $history)
                                                    <li><span>{{ $statusLabels[$history->status] ?? str($history->status)->replace('_', ' ')->title() }}</span><time>{{ $history->created_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</time></li>
                                                @empty
                                                    <li>No status updates yet.</li>
                                                @endforelse
                                            </ol>
                                            @if(in_array($order->status, ['placed', 'confirmed'], true))
                                                <form method="POST" action="{{ route('seller.orders.status', $order) }}" data-confirm="Cancel order {{ $order->order_number }}? Its items go back into your stock and the buyer is told." data-confirm-title="Cancel order?" data-confirm-button="Cancel order" data-confirm-tone="danger">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="order-cancel">Cancel order</button>
                                                </form>
                                            @endif
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="seller-empty">
                                    <i class="fas fa-box-open" aria-hidden="true"></i>
                                    <strong>No orders in this stage</strong>
                                    <span>Orders will appear here when they match this filter.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="seller-empty" data-search-empty="orders" hidden>No visible orders match your search.</p>

        @if($orders->hasPages())
            <div class="seller-pagination">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
