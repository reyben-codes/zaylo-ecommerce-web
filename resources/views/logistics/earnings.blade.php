@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Earnings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/wallet.css') }}?v={{ filemtime(public_path('css/wallet.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Earnings</h1>
            <p>You earn {{ $percent }}% of the shipping fee of every parcel your riders deliver, including free-shipping orders (ZAYLO covers the voucher). ZAYLO pays your company, and you pay your riders and branches.</p>
        </div>
    </section>

    <section class="seller-stat-grid" aria-label="Your earnings">
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-wallet" aria-hidden="true"></i> Earned</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['partner'], 2) }}</strong>
            <span class="seller-stat__note">{{ number_format($summary['parcels']) }} delivered {{ Str::plural('parcel', $summary['parcels']) }}</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-circle-check" aria-hidden="true"></i> Paid</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['paid'], 2) }}</strong>
            <span class="seller-stat__note">{{ number_format($history->count()) }} {{ Str::plural('payout', $history->count()) }}</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-hourglass-half" aria-hidden="true"></i> To be paid</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['owed'], 2) }}</strong>
            <span class="seller-stat__note">{{ number_format($summary['unpaidParcels']) }} {{ Str::plural('parcel', $summary['unpaidParcels']) }} not paid yet</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-truck" aria-hidden="true"></i> Shipping fees</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['fees'], 2) }}</strong>
            <span class="seller-stat__note">ZAYLO keeps {{ 100 - $percent }}%</span>
        </div>
    </section>

    @include('partials.wallet', ['cashOutRoute' => 'logistics.wallet.cash-out'])

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Payouts</h2><p>Payments ZAYLO has sent you.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Paid on</th><th>Parcels</th><th>Shipping fees</th><th>Amount</th><th>Reference</th></tr></thead>
                <tbody>
                    @forelse($history as $payout)
                        <tr>
                            <td>{{ $payout->paid_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td>{{ number_format($payout->parcels) }}</td>
                            <td>&#8369;{{ number_format($payout->shipping_fees, 2) }}</td>
                            <td><strong>&#8369;{{ number_format($payout->amount, 2) }}</strong></td>
                            <td>{{ $payout->reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="admin-empty-inline">No payouts yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Delivered parcels</h2><p>The latest 100 parcels and what you earn from each.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Parcel</th><th>Delivered</th><th>Shipping fee</th><th>Your {{ $percent }}%</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($parcels as $parcel)
                        <tr>
                            <td><strong>{{ $parcel['tracking'] }}</strong></td>
                            <td>{{ $parcel['delivered_at']?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}</td>
                            <td>&#8369;{{ number_format($parcel['fee'], 2) }}</td>
                            <td>&#8369;{{ number_format($parcel['partner'], 2) }}</td>
                            <td>
                                @if($parcel['payout_id'])
                                    <span class="commission-state commission-state--settled">Paid</span>
                                @else
                                    <span class="commission-state commission-state--pending">To be paid</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="admin-empty-inline">No delivered parcels yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
