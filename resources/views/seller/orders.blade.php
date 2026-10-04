<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZAYLO | Order Fulfillment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/seller-orders.css') }}?v={{ filemtime(public_path('css/seller-orders.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/seller-header.css') }}?v={{ filemtime(public_path('css/seller-header.css')) }}">
</head>
<body class="seller-workspace">
    @include('partials.seller-header')

    @include('partials.notifications')

    <div class="seller-sidebar-overlay" data-seller-sidebar-overlay></div>

    <div class="seller-shell">
        @include('partials.seller-sidebar')

        <main class="seller-orders-main">
            <section class="seller-orders-heading">
                <div>
                    <p class="seller-eyebrow">Seller Center</p>
                    <h1>Order fulfillment</h1>
                    <p>Prepare orders and manage delivery until your logistics integration is ready.</p>
                </div>
                @if(config('marketplace.seller_managed_delivery'))
                    <span class="fulfillment-mode"><i class="fas fa-route" aria-hidden="true"></i> Seller-managed delivery</span>
                @endif
            </section>

            <aside class="fulfillment-notice">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <div>
                    <strong>Temporary self-delivery workflow</strong>
                    <p>You control each parcel after packing. Update stages only when they happen—the buyer sees every update in their order history.</p>
                </div>
            </aside>

            <section class="order-stat-grid" aria-label="Order summary">
                <article><span>All orders</span><strong>{{ $counts['all'] }}</strong></article>
                <article><span>To prepare</span><strong>{{ $counts['to_prepare'] }}</strong></article>
                <article><span>In delivery</span><strong>{{ $counts['shipping'] }}</strong></article>
                <article><span>Delivered</span><strong>{{ $counts['delivered'] }}</strong></article>
            </section>

            @php
                $tabs = [
                    'all' => 'All',
                    'to_prepare' => 'To prepare',
                    'shipping' => 'Shipping',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                ];
                $statusLabels = [
                    'placed' => 'Placed',
                    'confirmed' => 'Confirmed',
                    'processing' => 'Processing',
                    'ready_for_pickup' => 'Ready to ship',
                    'picked_up' => 'Shipped',
                    'in_transit' => 'In transit',
                    'out_for_delivery' => 'Out for delivery',
                    'completed' => 'Delivered',
                    'cancelled' => 'Cancelled',
                ];
                $nextActions = [
                    'placed' => ['status' => 'confirmed', 'label' => 'Confirm order', 'icon' => 'fa-check'],
                    'confirmed' => ['status' => 'processing', 'label' => 'Start processing', 'icon' => 'fa-box-open'],
                    'processing' => ['status' => 'ready_for_pickup', 'label' => 'Ready to ship', 'icon' => 'fa-box'],
                    'ready_for_pickup' => ['status' => 'picked_up', 'label' => 'Mark as shipped', 'icon' => 'fa-truck-fast'],
                    'picked_up' => ['status' => 'in_transit', 'label' => 'Mark in transit', 'icon' => 'fa-route'],
                    'in_transit' => ['status' => 'out_for_delivery', 'label' => 'Out for delivery', 'icon' => 'fa-location-arrow'],
                    'out_for_delivery' => ['status' => 'completed', 'label' => 'Mark delivered', 'icon' => 'fa-circle-check'],
                ];
                if (! config('marketplace.seller_managed_delivery')) {
                    $nextActions = collect($nextActions)->except(['ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery'])->all();
                }
            @endphp

            <section class="orders-panel">
                <div class="orders-toolbar">
                    <nav class="order-tabs" aria-label="Filter seller orders">
                        @foreach($tabs as $key => $label)
                            <a href="{{ route('seller.orders', $key === 'all' ? [] : ['status' => $key]) }}"
                               class="order-tab{{ $filter === $key ? ' is-active' : '' }}"
                               @if($filter === $key) aria-current="page" @endif>
                                {{ $label }} <span>{{ $counts[$key] }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <label class="order-search">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <span class="sr-only">Search visible orders</span>
                        <input type="search" placeholder="Search orders" data-order-search>
                    </label>
                </div>

                <div class="orders-table-wrap">
                    <table class="orders-table">
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
                                <tr data-order-row data-order-search-text="{{ $searchText }}">
                                    <td>
                                        <strong class="order-reference">{{ $order->order_number }}</strong>
                                        <span class="order-date">{{ ($order->placed_at ?? $order->created_at)?->format('M j, Y · g:i A') }}</span>
                                        @if($order->shipment)
                                            <span class="tracking-number">{{ $order->shipment->tracking_number }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $order->buyer?->name ?? 'Buyer' }}</strong>
                                        <span>{{ $order->recipient_name }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $itemNames->first() ?? 'Order items' }}</strong>
                                        @if($order->items->count() > 1)
                                            <span>+ {{ $order->items->count() - 1 }} more</span>
                                        @endif
                                    </td>
                                    <td><strong>&#8369;{{ number_format($order->total, 2) }}</strong></td>
                                    <td>
                                        <span class="order-status order-status--{{ str($order->status)->replace('_', '-') }}">
                                            {{ $statusLabels[$order->status] ?? str($order->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="order-actions">
                                            @if($nextAction)
                                                <form method="POST" action="{{ route('seller.orders.status', $order) }}"
                                                      @if($nextAction['status'] === 'completed') data-confirm="Confirm that the buyer received this parcel? This will mark the payment paid and complete the order." @endif>
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $nextAction['status'] }}">
                                                    <button type="submit" class="order-action-primary">
                                                        <i class="fas {{ $nextAction['icon'] }}" aria-hidden="true"></i> {{ $nextAction['label'] }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="no-action">No action needed</span>
                                            @endif

                                            <details class="order-details">
                                                <summary>Details</summary>
                                                <div class="order-details-card">
                                                    <strong>Delivery details</strong>
                                                    <p>{{ $order->recipient_name }} · {{ $order->phone }}</p>
                                                    <p>{{ $order->shipping_address }}</p>
                                                    @if($order->shipment?->picked_up_at)
                                                        <p>Shipped: {{ $order->shipment->picked_up_at->format('M j, Y · g:i A') }}</p>
                                                    @endif
                                                    @if($order->shipment?->delivered_at)
                                                        <p>Delivered: {{ $order->shipment->delivered_at->format('M j, Y · g:i A') }}</p>
                                                    @endif
                                                    <strong>Status history</strong>
                                                    <ol>
                                                        @forelse($order->statusHistory as $history)
                                                            <li><span>{{ $statusLabels[$history->status] ?? str($history->status)->replace('_', ' ')->title() }}</span><time>{{ $history->created_at?->format('M j, g:i A') }}</time></li>
                                                        @empty
                                                            <li>No status updates yet.</li>
                                                        @endforelse
                                                    </ol>
                                                    @if(in_array($order->status, ['placed', 'confirmed'], true))
                                                        <form method="POST" action="{{ route('seller.orders.status', $order) }}" data-confirm="Cancel this order and return its items to stock?">
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
                                    <td colspan="6" class="orders-empty">
                                        <i class="fas fa-box-open" aria-hidden="true"></i>
                                        <strong>No orders in this stage</strong>
                                        <span>Orders will appear here when they match this filter.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="orders-search-empty" data-search-empty hidden>No visible orders match your search.</p>

                @if($orders->hasPages())
                    <div class="orders-pagination">{{ $orders->links() }}</div>
                @endif
            </section>
        </main>
    </div>

    <script src="{{ asset('js/notifications.js') }}?v={{ filemtime(public_path('js/notifications.js')) }}" defer></script>
    <script src="{{ asset('js/seller-orders.js') }}?v={{ filemtime(public_path('js/seller-orders.js')) }}" defer></script>
</body>
</html>
