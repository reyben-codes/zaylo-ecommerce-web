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
            <p>Riders collect the order total in cash for every cash-on-delivery parcel. Count each rider's cash when they time out and record what you received; any shortfall stays on record against the rider. GCash orders were paid to the seller, so riders collect nothing for them. From this cash your company keeps its {{ \App\Services\LogisticsPayouts::partnerPercent() }}% of the shipping fees and ZAYLO settles the rest with the sellers.</p>
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
