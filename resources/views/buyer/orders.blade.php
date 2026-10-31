@extends('layouts.app')

@section('title', 'My Orders · ZAYLO')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-reviews.css') }}?v={{ filemtime(public_path('css/product-reviews.css')) }}">
@endpush

@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        @include('partials.icons.order-package', ['class' => 'page-hero-svg'])
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
            'ready_for_pickup' => 'Your order is packed and ready to ship.',
            'assigned' => 'A courier has been assigned to your order.',
            'picked_up' => 'The seller has shipped your order.',
            'in_transit' => 'Your order is on its way.',
            'out_for_delivery' => 'Your order is out for delivery.',
            'delivered' => 'The rider delivered your order.',
            'completed' => 'Your order is complete.',
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

            @if($order->status === 'delivered')
                @php
                    $autoConfirmAt = \App\Services\OrderReceipts::autoConfirmAt($order);
                    $returnDeadline = $order->returnDeadline();
                    $isReturnActive = $order->isWithinReturnWindow();
                @endphp
                <section class="order-receipt" aria-label="Confirm you received this order">
                    <div>
                        <p><strong>Did you receive your order?</strong> Confirm it so the seller gets paid.</p>
                        @if($autoConfirmAt)
                            <p>If you don't, it is confirmed automatically on {{ $autoConfirmAt->timezone('Asia/Manila')->format('M j, Y · g:i A') }}. Not received? File a complaint before then.</p>
                        @endif
                        @if($isReturnActive && $returnDeadline)
                            <p style="margin-top:6px; font-size:0.83rem; color:#b45309; display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                                <span><strong>24-Hour Return Window:</strong> Damaged or incorrect items can be returned until <strong>{{ $returnDeadline->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</strong>.</span>
                            </p>
                        @elseif($order->isReturnWindowExpired() && $returnDeadline)
                            <p style="margin-top:6px; font-size:0.82rem; color:#64748b; display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                                <span>24-hour return window for damaged/wrong products ended on {{ $returnDeadline->timezone('Asia/Manila')->format('M j, Y · g:i A') }}.</span>
                            </p>
                        @endif
                    </div>
                    <div class="order-receipt__actions">
                        @if($order->shipment?->delivery_proof_path)
                            <a class="order-receipt__photo" href="{{ route('delivery-proof', $order->shipment) }}" target="_blank" rel="noopener"><i class="fas fa-camera" aria-hidden="true"></i> See the rider's photo</a>
                        @endif
                        <form method="POST" action="{{ route('buyer.orders.received', $order) }}"
                              data-confirm="Confirm that you received order {{ $order->order_number }}? This completes the order and pays the seller." data-confirm-title="Order received?" data-confirm-button="Yes, I received it">
                            @csrf
                            <button class="market-button" type="submit">Order received</button>
                        </form>
                    </div>
                </section>
            @endif

            <div class="order-delivery-truck-banner" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin:10px 0; font-size:.84rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <div>
                        <span style="color:#64748b; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; font-weight:600;">Delivery Courier Truck</span>
                        <p style="margin:2px 0 0; font-weight:600; color:#1e293b;">
                            <i class="fas fa-truck-moving" aria-hidden="true" style="color:#0284c7;"></i> {{ $order->courierName() }}
                        </p>
                    </div>
                    <div style="text-align:right;">
                        <span style="color:#64748b; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; font-weight:600;">Waiting Time &amp; Arrival Window</span>
                        <p style="margin:2px 0 0; color:#0f766e; font-weight:600;">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            @if(in_array($order->status, ['placed', 'confirmed', 'processing'], true))
                                Est. arrival {{ $order->placed_at ? $order->placed_at->addDays(3)->format('M j') . ' – ' . $order->placed_at->addDays(5)->format('M j') : '3–5 days' }}
                            @elseif(in_array($order->status, ['ready_for_pickup', 'assigned', 'picked_up', 'in_transit', 'out_for_delivery'], true))
                                Out on the road &middot; ETA {{ $order->placed_at ? $order->placed_at->addDays(2)->format('M j') : '1–2 days' }}
                            @elseif(in_array($order->status, ['delivered', 'completed'], true))
                                Delivered {{ ($order->delivered_at ?? $order->shipment?->delivered_at ?? $order->updated_at)?->format('M j, Y') }}
                            @else
                                {{ ucfirst($order->status) }}
                            @endif
                        </p>
                    </div>
                </div>

                @php
                    $timeline = [
                        ['key' => 'placed', 'label' => 'Placed', 'icon' => 'fa-clipboard-check'],
                        ['key' => 'processing', 'label' => 'Preparing', 'icon' => 'fa-box'],
                        ['key' => 'picked_up', 'label' => 'Truck Pickup', 'icon' => 'fa-truck-ramp-box'],
                        ['key' => 'in_transit', 'label' => 'In Transit', 'icon' => 'fa-truck-fast'],
                        ['key' => 'delivered', 'label' => 'Delivered', 'icon' => 'fa-house-circle-check'],
                        ['key' => 'completed', 'label' => 'Received', 'icon' => 'fa-circle-check'],
                    ];
                    $statusRanks = [
                        'placed' => 1,
                        'confirmed' => 2,
                        'processing' => 2,
                        'ready_for_pickup' => 2,
                        'assigned' => 3,
                        'picked_up' => 3,
                        'in_transit' => 4,
                        'out_for_delivery' => 4,
                        'delivered' => 5,
                        'completed' => 6,
                    ];
                    $currentRank = $statusRanks[$order->status] ?? 1;
                @endphp
                <div class="order-timeline-bar" style="display:grid; grid-template-columns: repeat(6, 1fr); gap:6px; margin-top:12px; text-align:center;">
                    @foreach($timeline as $idx => $step)
                        @php $isStepActive = $currentRank >= ($idx + 1); @endphp
                        <div style="padding:6px 4px; border-radius:6px; background:{{ $isStepActive ? '#ecfdf5' : '#f1f5f9' }}; border:1px solid {{ $isStepActive ? '#6ee7b7' : '#cbd5e1' }}; color:{{ $isStepActive ? '#047857' : '#94a3b8' }}; font-size:.72rem;">
                            <i class="fas {{ $step['icon'] }}" aria-hidden="true"></i>
                            <div style="font-weight:{{ $isStepActive ? '600' : 'normal' }}; margin-top:2px;">{{ $step['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <details class="order-handover-scan" style="margin:8px 0; font-size:.82rem;">
                <summary style="cursor:pointer; color:#0284c7; font-weight:600;"><i class="fas fa-barcode" aria-hidden="true"></i> Show delivery handover barcode for courier scan</summary>
                <div style="padding:12px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; margin-top:6px; text-align:center;">
                    <p style="margin:0 0 6px; color:#475569; font-size:.78rem;">Courier truck driver can scan this barcode upon doorstep delivery</p>
                    <div style="display:inline-block; max-width:100%; overflow-x:auto;">
                        {!! \App\Support\Code128::svg($order->order_number, 44, 2) !!}
                    </div>
                    <p style="margin:2px 0 0; font-family:monospace; font-weight:bold; letter-spacing:1px;">{{ $order->order_number }}</p>
                </div>
            </details>

            <div class="order-items">
                @foreach($order->items as $item)
                    <div class="summary-row">
                        <span>{{ $item->quantity }} &times; {{ $item->product_name }}</span>
                        <span>&#8369;{{ number_format($item->line_total, 2) }}</span>
                    </div>
                    @if($item->review)
                        <p class="review-meta">Your rating: {{ $item->review->rating }}/5 ★ · Review submitted</p>
                    @elseif(in_array($order->status, ['delivered', 'completed'], true) && $item->product_id)
                        <a class="order-review-link" href="{{ route('buyer.reviews.create', $item) }}" aria-label="Rate {{ $item->product_name }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3 2.78 5.63L21 9.54l-4.5 4.39 1.06 6.2L12 17.2l-5.56 2.93 1.06-6.2L3 9.54l6.22-.91Z"/></svg>
                            Rate product
                        </a>
                    @endif
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
                    <form method="POST" action="{{ route('buyer.orders.cancel', $order) }}"
                          data-confirm="Cancel order {{ $order->order_number }}? This cannot be undone." data-confirm-title="Cancel order?" data-confirm-button="Cancel order" data-confirm-tone="danger">
                        @csrf
                        <button class="text-button danger" type="submit">Cancel order</button>
                    </form>
                @endif
                @if($order->relationLoaded('disputes'))
                    @php $complaint = $order->disputes->first(); @endphp
                    @if($complaint)
                        <a class="text-button" href="{{ route('disputes.show', $complaint) }}" style="color:#0284c7; font-weight:600;">
                            <i class="fas fa-rotate-left" aria-hidden="true"></i> Return / Refund: {{ $complaint->return_status ? ucfirst(str_replace('_', ' ', $complaint->return_status)) : $complaint->status_label }}
                            @if($complaint->return_tracking_number)
                                ({{ $complaint->return_tracking_number }})
                            @endif
                        </a>
                    @elseif(in_array($order->status, ['delivered', 'completed'], true))
                        @if($order->isWithinReturnWindow())
                            <a class="text-button" href="{{ route('buyer.disputes.create', $order) }}" style="color:#b45309; font-weight:600;" title="Damaged or incorrect products can be returned within 24 hours of delivery">
                                <i class="fas fa-rotate-left" aria-hidden="true"></i> Return / Refund <span style="font-size:0.75rem; font-weight:normal; opacity:0.85;">(24h window active)</span>
                            </a>
                        @else
                            <a class="text-button" href="{{ route('buyer.disputes.create', $order) }}">
                                <i class="fas fa-flag" aria-hidden="true"></i> Report a problem
                            </a>
                        @endif
                    @elseif($order->status !== 'cancelled' && ! in_array($order->status, ['placed', 'confirmed'], true))
                        <a class="text-button" href="{{ route('buyer.disputes.create', $order) }}"><i class="fas fa-flag" aria-hidden="true"></i> Report a problem</a>
                    @endif
                @endif
            </footer>

            <details class="order-details">
                <summary>View order details</summary>
                <div class="order-details-grid">
                    <section>
                        <h2>Delivery details</h2>
                        <p><strong>Recipient:</strong> {{ $order->recipient_name }}</p>
                        <p><strong>Phone:</strong> {{ $order->phone }}</p>
                        <p class="order-address"><strong>Address:</strong> {{ $order->formattedShippingAddress() }}</p>
                        @if($order->shipment?->picked_up_at)
                            <p><strong>Picked up:</strong> {{ $order->shipment->picked_up_at->format('M j, Y · g:i A') }}</p>
                        @endif
                        @if($order->shipment?->delivered_at)
                            <p><strong>Delivered:</strong> {{ $order->shipment->delivered_at->format('M j, Y · g:i A') }}</p>
                        @endif
                        @if($order->shipment?->delivery_proof_path)
                            <p><strong>Proof of delivery:</strong> <a href="{{ route('delivery-proof', $order->shipment) }}" target="_blank" rel="noopener">View the rider's photo</a></p>
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
