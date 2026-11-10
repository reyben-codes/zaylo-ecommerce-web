@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Cash handover')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Cash on delivery</p>
            <h1>Cash handover</h1>
            <p>Riders retain ₱40 per COD parcel and hand the remaining cash to your hub. Count it before allocating the fixed ₱40 plus 10% product logistics share, the Admin share, and the seller's 80% product share. A parcel is fully settled only after its seller transfer is recorded.</p>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>COD settlements</h2><p>Verified handovers are allocated automatically. Record the seller transfer reference to complete a parcel's settlement.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Parcel</th><th>Received from rider</th><th>Logistics</th><th>Admin</th><th>Seller</th><th>Status</th><th>Seller transfer</th></tr></thead>
                <tbody>
                    @forelse($settlements as $settlement)
                        <tr>
                            <td><strong>{{ $settlement->shipment?->tracking_number ?? $settlement->shipment?->tracking_code ?? '#'.$settlement->shipment_id }}</strong><span class="seller-cell-note">{{ $settlement->seller?->name ?? 'Seller unavailable' }}</span></td>
                            <td>&#8369;{{ number_format($settlement->amount_received, 2) }}</td>
                            <td>&#8369;{{ number_format($settlement->logistics_earnings, 2) }}</td>
                            <td>&#8369;{{ number_format($settlement->admin_earnings, 2) }}</td>
                            <td><strong>&#8369;{{ number_format($settlement->seller_earnings, 2) }}</strong></td>
                            <td>
                                @if($settlement->status === \App\Models\LogisticsCodSettlement::SETTLED)
                                    <span class="commission-state commission-state--settled">Settled</span>
                                    <span class="seller-cell-note">{{ $settlement->settled_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} by {{ $settlement->settler?->name ?? '—' }}</span>
                                @else
                                    <span class="commission-state commission-state--pending">Awaiting seller transfer</span>
                                    <span class="seller-cell-note">Allocated by {{ $settlement->recorder?->name ?? '—' }}</span>
                                @endif
                            </td>
                            <td>
                                @if($settlement->status === \App\Models\LogisticsCodSettlement::SETTLED)
                                    {{ $settlement->seller_reference }}
                                @else
                                    <form method="POST" action="{{ route('logistics.cash.settlements.settle', $settlement) }}" class="payout-form"
                                          data-confirm="Confirm that ₱{{ number_format($settlement->seller_earnings, 2) }} was transferred to {{ $settlement->seller?->name ?? 'the seller' }}?" data-confirm-title="Record seller transfer?" data-confirm-button="Record transfer">
                                        @csrf
                                        <label class="sr-only" for="seller-reference-{{ $settlement->id }}">Seller transfer reference</label>
                                        <input id="seller-reference-{{ $settlement->id }}" name="seller_reference" maxlength="100" placeholder="Transfer reference" required>
                                        <button type="submit" class="seller-button seller-button--small">Settle</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="admin-empty-inline">No verified COD handovers are ready to settle.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Riders holding cash</h2><p>Delivered COD parcels whose cash was not handed over yet.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Rider</th><th>COD parcels</th><th>Cash to receive</th><th>Record handover</th></tr></thead>
                <tbody>
                    @forelse($owed as $row)
                        @php $riderUser = $row['rider']->user; $formError = $errors->hasAny(['received', 'note']) && (int) old('cash_rider') === $riderUser->id; @endphp
                        <tr>
                            <td><strong>{{ $riderUser->name }}</strong><span class="seller-cell-note">{{ $row['rider']->vehicle_type }}</span></td>
                            <td>{{ $row['parcels'] }}</td>
                            <td><strong>&#8369;{{ number_format($row['cash'], 2) }}</strong></td>
                            <td>
                                <form method="POST" action="{{ route('logistics.cash.record', $riderUser) }}" class="cash-handover-form"
                                      data-confirm="Record the cash you counted from {{ $riderUser->name }}?" data-confirm-title="Record handover?" data-confirm-button="Record">
                                    @csrf
                                    <input type="hidden" name="cash_rider" value="{{ $riderUser->id }}">
                                    <label class="sr-only" for="cash-{{ $riderUser->id }}">Cash received from {{ $riderUser->name }}</label>
                                    <input id="cash-{{ $riderUser->id }}" name="received" type="number" step="0.01" min="0" value="{{ $formError ? old('received') : number_format($row['cash'], 2, '.', '') }}" required>
                                    <label class="sr-only" for="cash-note-{{ $riderUser->id }}">Note (needed when short)</label>
                                    <input id="cash-note-{{ $riderUser->id }}" name="note" maxlength="255" placeholder="Note (needed when short)" value="{{ $formError ? old('note') : '' }}">
                                    <button type="submit" class="seller-button seller-button--small"><i class="fas fa-check" aria-hidden="true"></i> Record</button>
                                </form>
                                @if($formError)<p class="seller-field__error" role="alert">{{ $errors->first('note') ?: $errors->first('received') }}</p>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><p class="admin-empty-inline">No rider is holding cash right now.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Handovers</h2><p>The latest cash counted at the hub.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>When</th><th>Rider</th><th>Parcels</th><th>Expected</th><th>Received</th><th>Short</th></tr></thead>
                <tbody>
                    @forelse($history as $handover)
                        <tr>
                            <td>{{ $handover->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}<span class="seller-cell-note">by {{ $handover->recorder?->name ?? '—' }}</span></td>
                            <td>{{ $handover->rider?->name }}</td>
                            <td>{{ $handover->parcels }}</td>
                            <td>&#8369;{{ number_format($handover->expected, 2) }}</td>
                            <td>&#8369;{{ number_format($handover->received, 2) }}</td>
                            <td>
                                @if($handover->shortfall > 0)
                                    <span class="commission-state commission-state--pending">&#8369;{{ number_format($handover->shortfall, 2) }}</span>
                                    @if($handover->note)<span class="seller-cell-note">{{ $handover->note }}</span>@endif
                                @else
                                    <span class="commission-state commission-state--settled">None</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><p class="admin-empty-inline">No handovers recorded yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
