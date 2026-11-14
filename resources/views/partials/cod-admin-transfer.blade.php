@if($settlement->admin_settled_at)
    <span class="seller-cell-note">Transfer recorded: {{ $settlement->admin_reference }} &middot; {{ $settlement->admin_settled_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</span>
@elseif((float) $settlement->admin_earnings === 0.0)
    <span class="seller-cell-note">No Admin transfer due</span>
@elseif(\Illuminate\Support\Facades\Schema::hasColumn('logistics_cod_settlements', 'admin_reference'))
    @php
        $fromAdmin = $settlement->admin_earnings < 0;
        $canRecord = $fromAdmin ? auth()->user()->hasRole('admin') : auth()->user()->hasRole('logistics');
    @endphp
    <span class="seller-cell-note">{{ $fromAdmin ? 'Admin owes Logistics' : 'Logistics owes Admin' }}: &#8369;{{ number_format(abs($settlement->admin_earnings), 2) }}</span>
    @if($canRecord)
        <form method="POST" action="{{ route($fromAdmin ? 'admin.cod-commissions.transfer' : 'logistics.cash.settlements.admin-transfer', $settlement) }}" class="payout-form">
            @csrf
            <label class="sr-only" for="admin-reference-{{ $settlement->id }}">Admin transfer reference</label>
            <input id="admin-reference-{{ $settlement->id }}" name="admin_reference" maxlength="100" placeholder="Reference after sending" required>
            <button type="submit" class="seller-button seller-button--small">Record sent transfer</button>
        </form>
    @endif
@endif
