@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Admin Wallet')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Platform Treasury</p>
            <h1>Admin Wallet & Balances</h1>
            <p>Unified financial dashboard for the ZAYLO platform. Monitor gateway escrow funds held from online and GCash orders, pending seller withdrawals, pending rider sweldo, and settled platform commissions.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('admin.rider-withdrawals') }}" class="seller-button"><i class="fa-solid fa-motorcycle" aria-hidden="true"></i> Rider sweldo (GCash)</a>
            <a href="{{ route('admin.seller-withdrawals') }}" class="seller-button seller-button--secondary"><i class="fa-solid fa-wallet" aria-hidden="true"></i> Seller payouts</a>
        </div>
    </section>

    @if(! config('escrow.live') && ! \App\Services\DemoPayments::enabled())
        <aside class="seller-notice">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <div><strong>Test payment mode</strong><p>Sandbox gateway transactions do not represent real funds. Do not send actual bank transfers for test checkouts.</p></div>
        </aside>
    @endif

    <section class="seller-stat-grid" aria-label="Platform financial overview">
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fa-solid fa-vault" aria-hidden="true"></i> Escrow held funds</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($escrowHeld, 2) }}</strong>
            <span class="seller-stat__note">Held online payments awaiting delivery confirmation</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fa-solid fa-store" aria-hidden="true"></i> Pending seller payouts</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($sellerPendingTotal, 2) }}</strong>
            <span class="seller-stat__note"><a href="{{ route('admin.seller-withdrawals') }}">{{ $sellerPendingCount }} {{ Str::plural('request', $sellerPendingCount) }} to review &rarr;</a></span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fa-solid fa-motorcycle" aria-hidden="true"></i> Pending rider sweldo</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($riderPendingTotal, 2) }}</strong>
            <span class="seller-stat__note"><a href="{{ route('admin.rider-withdrawals') }}">{{ $riderPendingCount }} {{ Str::plural('request', $riderPendingCount) }} to pay &rarr;</a></span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fa-solid fa-cash-register" aria-hidden="true"></i> COD commission pending</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($codAwaitingTransfer, 2) }}</strong>
            <span class="seller-stat__note"><a href="{{ route('admin.cod-commissions') }}">Hub remittances awaiting transfer &rarr;</a></span>
        </div>
    </section>

    <div class="admin-grid-2">
        <section class="seller-panel">
            <header class="seller-panel__header">
                <div>
                    <h2>Recent seller payouts</h2>
                    <p>Latest transfers dispatched to sellers</p>
                </div>
                <a href="{{ route('admin.seller-withdrawals') }}" class="seller-button seller-button--small seller-button--secondary">View all</a>
            </header>
            <div class="seller-table-wrap">
                <table class="seller-table workspace-table admin-money">
                    <thead><tr><th>Seller</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
                    <tbody>
                        @forelse($recentSellerPayouts as $payout)
                            <tr>
                                <td><strong>{{ $payout->user?->name ?? 'Seller' }}</strong><span class="seller-cell-note">{{ $payout->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</span></td>
                                <td><strong>&#8369;{{ number_format($payout->amount_minor / 100, 2) }}</strong></td>
                                <td>{{ strtoupper($payout->method) }}</td>
                                <td><span class="seller-cell-note">{{ $payout->reference ?? '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><p class="admin-empty-inline">No seller payouts sent yet.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="seller-panel">
            <header class="seller-panel__header">
                <div>
                    <h2>Recent rider sweldo payouts</h2>
                    <p>Latest GCash sweldo sent to riders</p>
                </div>
                <a href="{{ route('admin.rider-withdrawals') }}" class="seller-button seller-button--small seller-button--secondary">View all</a>
            </header>
            <div class="seller-table-wrap">
                <table class="seller-table workspace-table admin-money">
                    <thead><tr><th>Rider</th><th>Amount</th><th>GCash</th><th>Reference</th></tr></thead>
                    <tbody>
                        @forelse($recentRiderPayouts as $payout)
                            <tr>
                                <td><strong>{{ $payout->rider?->name ?? 'Rider' }}</strong><span class="seller-cell-note">{{ $payout->processed_at?->timezone('Asia/Manila')->format('M j, Y') }}</span></td>
                                <td><strong>&#8369;{{ number_format($payout->amount, 2) }}</strong></td>
                                <td>{{ $payout->formattedNumber() }}</td>
                                <td><span class="seller-cell-note">{{ $payout->reference ?? '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><p class="admin-empty-inline">No rider sweldo paid yet.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div>
                <h2>Gateway payment holds (Escrow)</h2>
                <p>Prepaid orders held in escrow until buyer confirmation or delivery completion</p>
            </div>
        </header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Order</th><th>Amount</th><th>Gateway Fee</th><th>Status</th><th>Reference</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse($escrows as $escrow)
                        <tr>
                            <td><strong>{{ $escrow->order?->order_number ?? $escrow->order?->reference ?? 'Order #'.$escrow->order_id }}</strong><span class="seller-cell-note">{{ \App\Services\DemoPayments::isDemo($escrow) ? 'GCash Demo' : ($escrow->livemode ? 'Live' : 'Test') }}</span></td>
                            <td><strong>&#8369;{{ number_format($escrow->amount_minor / 100, 2) }}</strong></td>
                            <td>&#8369;{{ number_format($escrow->gateway_fee_minor / 100, 2) }}</td>
                            <td><span class="commission-state {{ $escrow->status === 'released' ? 'commission-state--settled' : '' }}">{{ ucfirst(str_replace('_', ' ', $escrow->status)) }}</span></td>
                            <td><span class="seller-cell-note">{{ $escrow->gateway_payment ?? 'Pending' }}</span></td>
                            <td>{{ $escrow->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><p class="admin-empty-inline">No gateway payments recorded yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
