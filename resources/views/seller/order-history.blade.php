@extends('layouts.seller')
@section('title', 'Order history')
@section('content')
    <section class="seller-page-heading">
        <div><p class="seller-eyebrow">Seller Center</p><h1>Order history</h1><p>Past orders stay here. Filter by order date in Philippine time to review each day's delivered, cancelled, and refunded orders.</p></div>
        <a class="seller-button seller-button--secondary" href="{{ route('seller.orders') }}">Manage orders</a>
    </section>
    <section class="seller-panel">
        <form method="GET" class="seller-filter-bar">
            <label class="seller-field">Order date<input type="date" name="date" value="{{ $date }}"></label>
            <label class="seller-field">Status<select name="status">
                @foreach(['all' => 'All orders', 'delivered' => 'Delivered / completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'] as $key => $label)
                    <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>
                @endforeach
            </select></label>
            <button class="seller-button" type="submit">Filter history</button>
            <a class="seller-link" href="{{ route('seller.orders.history') }}">All dates</a>
        </form>
        @foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
        <div class="seller-table-wrap">
            <table class="seller-table">
                <thead><tr><th>Order / date</th><th>Buyer</th><th>Items</th><th>Status</th><th>Total</th><th>Refund</th></tr></thead>
                <tbody>
                @forelse($orders as $record)
                    @php
                        $order = $legacy ? $record : $record->order;
                        $refund = $order?->disputes->whereNotNull('refunded_at')->sum('refund_amount') ?? 0;
                    @endphp
                    <tr>
                        <td><strong>{{ $order?->order_number }}</strong><p>{{ ($legacy ? $record->placed_at : $record->created_at)?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p></td>
                        <td>{{ $order?->buyer?->name ?? 'Buyer' }}</td>
                        <td>@foreach($record->items as $item)<p>{{ $item->product_name }} &times; {{ $item->quantity }}</p>@endforeach</td>
                        <td>{{ \App\Http\Controllers\SellerController::STATUS_LABELS[$record->status] ?? str($record->status)->headline() }}</td>
                        <td>&#8369;{{ number_format($legacy ? $record->total : ($record->subtotal_minor + $record->shipping_fee_minor) / 100, 2) }}</td>
                        <td>{{ $refund > 0 ? 'Refunded: PHP '.number_format($refund, 2) : 'None' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No orders for these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </section>
@endsection
