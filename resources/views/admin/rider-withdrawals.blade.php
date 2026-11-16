@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Rider payouts (GCash)')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Platform Earnings & Payouts</p>
            <h1>Rider payouts (GCash)</h1>
            <p>Rider earnings from GCash (online) orders and completed delivery legs are credited to rider wallets and funded via platform payout. Review pending rider withdrawal requests, transfer funds to their GCash accounts, and record the 13-character GCash reference number to complete payment.</p>
        </div>
    </section>

    @unless($ready)
        <aside class="seller-notice">
            <i class="fas fa-database" aria-hidden="true"></i>
            <div><strong>Rider wallets are not set up on this database yet</strong><p>Run the latest migrations to start tracking rider commissions and withdrawals.</p></div>
        </aside>
    @endunless

    @if(session('status'))
        <aside class="seller-notice seller-notice--success" role="status">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <div><strong>Success</strong><p>{{ session('status') }}</p></div>
        </aside>
    @endif

    @if($errors->any())
        <aside class="seller-notice" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <div><strong>The withdrawal was not updated</strong><p>{{ $errors->first() }}</p></div>
        </aside>
    @endif

    <section class="seller-stat-grid" aria-label="Withdrawal totals">
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Pending rider payouts</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($pendingTotal, 2) }}</strong>
            <span class="seller-stat__note">{{ number_format($pending->count()) }} {{ Str::plural('request', $pending->count()) }}</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-circle-check" aria-hidden="true"></i> Total rider payouts sent</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($paidTotal, 2) }}</strong>
            <span class="seller-stat__note">All-time GCash transfers</span>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Waiting for GCash transfer</h2><p>Rider withdrawal requests awaiting payment. Send via GCash and enter the 13-character reference.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Requested</th><th>Rider</th><th>Logistics Hub</th><th>GCash account</th><th>Amount</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse($pending as $withdrawal)
                        <tr>
                            <td>{{ $withdrawal->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td><strong>{{ $withdrawal->rider?->name ?? '—' }}</strong>@if($withdrawal->rider?->email)<span class="seller-cell-note">{{ $withdrawal->rider->email }}</span>@endif</td>
                            <td>{{ $withdrawal->provider?->name ?? 'Hub partner' }}</td>
                            <td><strong>{{ $withdrawal->formattedNumber() }}</strong><span class="seller-cell-note">{{ $withdrawal->gcash_name }}</span></td>
                            <td><strong>&#8369;{{ number_format($withdrawal->amount, 2) }}</strong></td>
                            <td>
                                <form method="POST" action="{{ route('admin.rider-withdrawals.pay', $withdrawal) }}" class="payout-form"
                                      data-confirm="Record &#8369;{{ number_format($withdrawal->amount, 2) }} as sent to GCash {{ $withdrawal->formattedNumber() }} ({{ $withdrawal->gcash_name }})? Do this after you have sent the money."
                                      data-confirm-title="Mark as sent?" data-confirm-button="Mark as sent">
                                    @csrf
                                    <label class="sr-only" for="withdrawal-reference-{{ $withdrawal->id }}">GCash reference number</label>
                                    <input type="text" id="withdrawal-reference-{{ $withdrawal->id }}" name="reference" minlength="13" maxlength="13" pattern="[A-Z0-9]{13}" title="13 uppercase letters or numbers" placeholder="REF20261115AB" autocomplete="off" required>
                                    <button type="submit" class="seller-button"><i class="fas fa-paper-plane" aria-hidden="true"></i> Record payment</button>
                                </form>
                                <form method="POST" action="{{ route('admin.rider-withdrawals.reject', $withdrawal) }}" class="payout-form"
                                      data-confirm="Reject this withdrawal? &#8369;{{ number_format($withdrawal->amount, 2) }} goes back to the rider's wallet."
                                      data-confirm-title="Reject withdrawal?" data-confirm-button="Reject">
                                    @csrf
                                    <label class="sr-only" for="withdrawal-reason-{{ $withdrawal->id }}">Reason for rejecting</label>
                                    <input type="text" id="withdrawal-reason-{{ $withdrawal->id }}" name="rejection_reason" maxlength="255" placeholder="Reason, e.g. wrong GCash name" autocomplete="off" required>
                                    <button type="submit" class="seller-button seller-button--secondary"><i class="fas fa-xmark" aria-hidden="true"></i> Reject</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><p class="admin-empty-inline">No rider payout requests pending.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Rider payout history</h2><p>The latest 50 paid or rejected rider withdrawals.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Processed</th><th>Rider</th><th>GCash account</th><th>Amount</th><th>Result</th><th>Processed by</th></tr></thead>
                <tbody>
                    @forelse($history as $withdrawal)
                        <tr>
                            <td>{{ $withdrawal->processed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}</td>
                            <td>{{ $withdrawal->rider?->name ?? '—' }}</td>
                            <td>{{ $withdrawal->formattedNumber() }}<span class="seller-cell-note">{{ $withdrawal->gcash_name }}</span></td>
                            <td>&#8369;{{ number_format($withdrawal->amount, 2) }}</td>
                            <td>
                                @if(in_array($withdrawal->status, [\App\Models\RiderWithdrawal::PAID, \App\Models\RiderWithdrawal::RELEASED], true))
                                    <span class="commission-state commission-state--settled">{{ $withdrawal->statusLabel() }}</span><span class="seller-cell-note">Ref. {{ $withdrawal->reference }}</span>
                                    @if($withdrawal->received_at)<span class="seller-cell-note">Receipt confirmed {{ $withdrawal->received_at->timezone('Asia/Manila')->format('M j, g:i A') }}</span>@endif
                                @else
                                    <span class="commission-state">Rejected</span><span class="seller-cell-note">{{ $withdrawal->rejection_reason }}</span>
                                @endif
                            </td>
                            <td>{{ $withdrawal->processor?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><p class="admin-empty-inline">No rider payouts recorded yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
