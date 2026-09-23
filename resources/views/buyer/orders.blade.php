@extends('layouts.app')

@section('title', 'My Orders · ZAYLO')

@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-box" aria-hidden="true"></i>
        <div>
            <h1>My Orders</h1>
            <p>Follow your orders from checkout to delivery.</p>
        </div>
    </div>
</div>

<div class="page-content orders-page">
    @php
        $tabs = [
            'all' => 'All orders',
            'to_ship' => 'To ship',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
        $statusMessages = [
            'placed' => 'Order placed. Waiting for the seller to confirm.',
            'confirmed' => 'The seller has confirmed your order.',
            'processing' => 'The seller is preparing your items.',
            'ready_for_pickup' => 'Your order is ready for courier pickup.',
            'assigned' => 'A courier has been assigned to your order.',
            'picked_up' => 'The courier has picked up your order.',
            'in_transit' => 'Your order is on its way.',
            'completed' => 'Your order has been delivered.',
            'cancelled' => 'This order was cancelled.',
        ];
    @endphp

    <nav class="order-tabs" aria-label="Filter orders by status">
        @foreach($tabs as $key => $label)
            <a href="{{ route('buyer.orders', $key === 'all' ? [] : ['status' => $key]) }}"
               class="order-tab {{ $filter === $key ? 'is-active' : '' }}"
               @if($filter === $key) aria-current="page" @endif>
                {{ $label }} <span class="order-tab-count">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @forelse($orders as $order)
        <article class="order-card order-status-card">
            <header>
                <div>
                    <strong>Order {{ $order->order_number }}</strong>
                    <p>Placed {{ $order->placed_at?->format('M j, Y · g:i A') }}</p>
                </div>
                <span class="status-pill status-{{ $order->status }}">{{ str($order->status)->replace('_', ' ')->title() }}</span>
            </header>

            <p class="order-status-message">{{ $statusMessages[$order->status] ?? 'Your order status has been updated.' }}</p>

            <div class="order-items">
                @foreach($order->items as $item)
                    <div class="summary-row">
                        <span>{{ $item->quantity }} &times; {{ $item->product_name }}</span>
                        <span>&#8369;{{ number_format($item->line_total, 2) }}</span>
                    </div>
                @endforeach
            </div>

            @if($order->shipment)
                <p class="order-tracking"><i class="fas fa-truck" aria-hidden="true"></i>
                    Shipment: {{ str($order->shipment->status)->replace('_', ' ')->title() }}
                    <span>Tracking no. {{ $order->shipment->tracking_number }}</span>
                </p>
            @endif

            <footer>
                <strong>Total: &#8369;{{ number_format($order->total, 2) }}</strong>
                <span>{{ strtoupper($order->payment_method) }} · Payment {{ str($order->payment?->status ?? 'pending')->replace('_', ' ')->title() }}</span>
                @if(in_array($order->status, ['placed', 'confirmed'], true))
                    <form method="POST" action="{{ route('buyer.orders.cancel', $order) }}">
                        @csrf
                        <button class="text-button danger" type="submit">Cancel order</button>
                    </form>
                @endif
            </footer>

            <details class="order-details">
                <summary>View order details</summary>
                <div class="order-details-grid">
                    <section>
                        <h2>Delivery details</h2>
                        <p><strong>Recipient:</strong> {{ $order->recipient_name }}</p>
                        <p><strong>Phone:</strong> {{ $order->phone }}</p>
                        <p class="order-address"><strong>Address:</strong> {{ $order->shipping_address }}</p>
                        @if($order->shipment?->picked_up_at)
                            <p><strong>Picked up:</strong> {{ $order->shipment->picked_up_at->format('M j, Y · g:i A') }}</p>
                        @endif
                        @if($order->shipment?->delivered_at)
                            <p><strong>Delivered:</strong> {{ $order->shipment->delivered_at->format('M j, Y · g:i A') }}</p>
                        @endif
                        @if($order->notes)
                            <p><strong>Order note:</strong> {{ $order->notes }}</p>
                        @endif
                    </section>
                    <section>
                        <h2>Order summary</h2>
                        <p><strong>Items:</strong> &#8369;{{ number_format($order->subtotal, 2) }}</p>
                        <p><strong>Shipping:</strong> &#8369;{{ number_format($order->shipping_fee + $order->shipping_discount, 2) }}</p>
                        @if($order->shipping_discount > 0)
                            <p><strong>Free shipping voucher ({{ $order->voucher_code }}):</strong> -&#8369;{{ number_format($order->shipping_discount, 2) }}</p>
                        @endif
                        <p><strong>Total:</strong> &#8369;{{ number_format($order->total, 2) }}</p>
                        <h2 class="order-history-title">Status history</h2>
                        @forelse($order->statusHistory as $change)
                            <p class="order-history-entry">
                                <strong>{{ str($change->status)->replace('_', ' ')->title() }}</strong>
                                <span>{{ $change->created_at?->format('M j, Y · g:i A') }}</span>
                            </p>
                        @empty
                            <p>No status updates yet.</p>
                        @endforelse
                    </section>
                </div>
            </details>
        </article>
    @empty
        <div class="placeholder-card order-empty-state">
            <i class="fas fa-box-open" aria-hidden="true"></i>
            <h2>{{ $filter === 'all' ? 'No orders yet' : 'No '.$tabs[$filter].' orders' }}</h2>
            <p>{{ $filter === 'all' ? 'Your purchases will appear here after checkout.' : 'Orders at this stage will appear here.' }}</p>
            @if($filter === 'all')
                <a href="{{ route('products.index') }}" class="market-button">Browse products</a>
            @else
                <a href="{{ route('buyer.orders') }}" class="market-button">View all orders</a>
            @endif
        </div>
    @endforelse

    {{ $orders->links() }}
</div>
@endsection
