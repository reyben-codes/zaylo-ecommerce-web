@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Logistics payouts')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Finance</p>
            <h1>Logistics payouts</h1>
            <p>For new orders, Admin and Logistics each earn {{ $percent }}% of product value. Logistics also receives shipping and pays its riders at the rate saved with each order. Seller shipping vouchers are funded by that seller; global Admin shipping vouchers are funded by ZAYLO. Gross partner payouts include rider funds. New COD allocations are handled through verified hub handovers and are not paid again here. Historical allocations retain their recorded policy.</p>
        </div>
    </section>

    @unless($ready)
        <aside class="seller-notice">
            <i class="fas fa-database" aria-hidden="true"></i>
            <div><strong>Payouts are not set up on this database yet</strong><p>Run the latest migrations to start tracking what partners are owed.</p></div>
        </aside>
    @endunless

    <section class="seller-stat-grid" aria-label="Shipping fee split">
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-truck" aria-hidden="true"></i> Shipping fees</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['fees'], 2) }}</strong>
            <span class="seller-stat__note">{{ number_format($summary['parcels']) }} delivered {{ Str::plural('parcel', $summary['parcels']) }}</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-building" aria-hidden="true"></i> ZAYLO's share</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['zaylo'], 2) }}</strong>
            <span class="seller-stat__note">Recorded Admin earnings after shipping subsidies</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-circle-check" aria-hidden="true"></i> Paid to partners</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['paid'], 2) }}</strong>
            <span class="seller-stat__note">Of &#8369;{{ number_format($summary['partner'], 2) }} earned, including &#8369;{{ number_format($summary['rider'], 2) }} rider commissions</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Owed to partners</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['owed'], 2) }}</strong>
            <span class="seller-stat__note">{{ number_format($summary['unpaidParcels']) }} unpaid {{ Str::plural('parcel', $summary['unpaidParcels']) }}</span>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>By partner</h2><p>Pay a partner after sending them the money; every delivered parcel not paid yet is included.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Partner</th><th>Delivered</th><th>Shipping fees</th><th>Partner gross</th><th>Rider earnings</th><th>Admin net</th><th>Paid</th><th>Owed</th><th><span class="sr-only">Pay</span></th></tr></thead>
                <tbody>
                    @forelse($byProvider as $partner)
                        <tr>
                            <td><strong>{{ $partner['provider'] }}</strong></td>
                            <td>{{ number_format($partner['parcels']) }}</td>
                            <td>&#8369;{{ number_format($partner['fees'], 2) }}</td>
                            <td>&#8369;{{ number_format($partner['partner'], 2) }}</td>
                            <td>&#8369;{{ number_format($partner['rider'], 2) }}</td>
                            <td>&#8369;{{ number_format($partner['zaylo'], 2) }}</td>
                            <td>&#8369;{{ number_format($partner['paid'], 2) }}</td>
                            <td><strong>&#8369;{{ number_format($partner['owed'], 2) }}</strong>@if($partner['unpaidParcels'])<span class="seller-cell-note">{{ $partner['unpaidParcels'] }} {{ Str::plural('parcel', $partner['unpaidParcels']) }}</span>@endif</td>
                            <td>
                                @if($partner['owed'] > 0 || $partner['unpaidParcels'] > 0)
                                    <form method="POST" action="{{ route('admin.logistics-payouts.pay', $partner['provider_id']) }}" class="payout-form"
                                          data-confirm="Record a payout of ₱{{ number_format($partner['owed'], 2) }} to {{ $partner['provider'] }} for {{ $partner['unpaidParcels'] }} delivered {{ Str::plural('parcel', $partner['unpaidParcels']) }}? Do this after you have sent the money."
                                          data-confirm-title="Mark as paid?" data-confirm-button="Mark as paid">
                                        @csrf
                                        <label class="sr-only" for="payout-reference-{{ $partner['provider_id'] }}">Payment reference for {{ $partner['provider'] }}</label>
                                        <input type="text" id="payout-reference-{{ $partner['provider_id'] }}" name="reference" maxlength="100" placeholder="Transfer reference" autocomplete="off" required>
                                        <button type="submit" class="seller-button"><i class="fas fa-money-bill-transfer" aria-hidden="true"></i> Mark as paid</button>
                                    </form>
                                @else
                                    <span class="commission-state commission-state--settled">No batch payout due</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><p class="admin-empty-inline">No delivered parcels yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Payout history</h2><p>The latest 50 payouts.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Paid on</th><th>Partner</th><th>Parcels</th><th>Shipping fees</th><th>Amount paid</th><th>Reference</th><th>Recorded by</th></tr></thead>
                <tbody>
                    @forelse($history as $payout)
                        <tr>
                            <td>{{ $payout->paid_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td>{{ $payout->provider?->name ?? '—' }}</td>
                            <td>{{ number_format($payout->parcels) }}</td>
                            <td>&#8369;{{ number_format($payout->shipping_fees, 2) }}</td>
                            <td><strong>&#8369;{{ number_format($payout->amount, 2) }}</strong><span class="seller-cell-note">{{ (int) round($payout->partner_share * 100) }}% share</span></td>
                            <td>{{ $payout->reference ?? '—' }}</td>
                            <td>{{ $payout->payer?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="admin-empty-inline">No payouts recorded yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
