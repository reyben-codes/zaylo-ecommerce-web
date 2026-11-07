@extends('layouts.seller')

@section('title', 'Settlement')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Settlement</h1>
            <p>What happens to the money of your completed orders. ZAYLO keeps a {{ $commissionPercent }}% commission on your items; shipping goes to the logistics partner; voucher discounts you joined come off your share; free shipping from ZAYLO costs you nothing.</p>
        </div>
    </section>

    <section class="seller-stat-grid" aria-label="Your balance">
        <article class="seller-stat"><span class="seller-stat__label">ZAYLO owes you (COD)</span><strong class="seller-stat__value">&#8369;{{ number_format($owed_to_seller, 2) }}</strong></article>
        <article class="seller-stat"><span class="seller-stat__label">You owe ZAYLO (GCash)</span><strong class="seller-stat__value">&#8369;{{ number_format($owed_by_seller, 2) }}</strong></article>
        <article class="seller-stat"><span class="seller-stat__label">{{ $balance >= 0 ? 'Payout due to you' : 'Due from you' }}</span><strong class="seller-stat__value">&#8369;{{ number_format(abs($balance), 2) }}</strong></article>
    </section>

    <aside class="seller-notice">
        <i class="fas fa-circle-info" aria-hidden="true"></i>
        <div>
            <strong>How it is worked out</strong>
            <p><b>Cash on delivery:</b> the rider collects what the buyer pays and hands it to the logistics hub. ZAYLO then owes you your items, less any voucher you joined, less the {{ $commissionPercent }}% commission.</p>
            <p><b>GCash:</b> the buyer paid you directly, shipping included. You owe ZAYLO the {{ $commissionPercent }}% commission plus the shipping the buyer paid (ZAYLO pays the logistics partner). It is taken from your next cash-on-delivery payout.</p>
        </div>
    </aside>

    <section class="seller-panel">
        <div class="seller-table-wrap">
            <table class="seller-table">
                <thead><tr><th>Order</th><th>Paid by</th><th>Items</th><th>Voucher</th><th>Commission</th><th>You keep</th><th>Settles as</th></tr></thead>
                <tbody>
                    @forelse($orders as $row)
                        <tr>
                            <td><strong>{{ $row['order']->order_number }}</strong><span class="seller-cell-note">{{ $row['order']->placed_at?->timezone('Asia/Manila')->format('M j, Y') }}</span></td>
                            <td>{{ $row['method'] === 'gcash' ? 'GCash (to you)' : 'Cash on delivery' }}</td>
                            <td>&#8369;{{ number_format($row['items'], 2) }}</td>
                            <td>{{ $row['voucher_by_seller'] > 0 ? '−₱'.number_format($row['voucher_by_seller'], 2) : '—' }}</td>
                            <td>−&#8369;{{ number_format($row['commission'], 2) }}</td>
                            <td><strong>&#8369;{{ number_format($row['seller_keeps'], 2) }}</strong></td>
                            <td>
                                @if($row['method'] === 'gcash')
                                    You owe &#8369;{{ number_format($row['seller_owes_zaylo'], 2) }}
                                @else
                                    ZAYLO owes &#8369;{{ number_format($row['zaylo_owes_seller'], 2) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="seller-empty"><i class="fas fa-scale-balanced" aria-hidden="true"></i><strong>No completed orders yet</strong><span>Orders show here once the buyer has them.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
