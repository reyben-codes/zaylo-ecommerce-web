{{-- Review actions for one rider application. Expects $rider. --}}
<div class="review-actions">
    @if($rider->status === 'rejected')
        <span class="seller-cell-note">Rejected · a rejected application cannot be approved</span>
    @endif

    @if($rider->status === 'pending')
        <form method="POST" action="{{ route('logistics.riders.update', $rider) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-check" aria-hidden="true"></i> Approve</button>
        </form>
    @endif

    @if($rider->status === 'approved')
        {{-- Pausing keeps the account but stops new parcel assignments. --}}
        <form method="POST" action="{{ route('logistics.riders.availability', $rider) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="available" value="{{ $rider->is_active ? 0 : 1 }}">
            <button type="submit" class="seller-button seller-button--small seller-button--secondary">
                <i class="fas {{ $rider->is_active ? 'fa-pause' : 'fa-play' }}" aria-hidden="true"></i> {{ $rider->is_active ? 'Pause' : 'Resume' }}
            </button>
        </form>
    @endif

    @if(in_array($rider->status, ['pending', 'approved'], true))
        @php $action = $rider->status === 'pending' ? 'reject' : 'suspend'; @endphp
        <details class="review-reason" data-smooth-disclosure>
            <summary class="seller-button seller-button--small seller-button--danger">
                <i class="fas {{ $action === 'reject' ? 'fa-xmark' : 'fa-ban' }}" aria-hidden="true"></i> {{ $action === 'reject' ? 'Reject' : 'Suspend' }}
            </summary>
            <div data-disclosure-content>
                <form method="POST" action="{{ route('logistics.riders.update', $rider) }}" class="review-reason__form"
                      data-confirm="{{ $action === 'reject' ? 'Reject '.$rider->user?->name.'\'s application?' : 'Suspend '.$rider->user?->name.'? They will not get new parcels until reinstated.' }}"
                      data-confirm-title="{{ $action === 'reject' ? 'Reject rider?' : 'Suspend rider?' }}" data-confirm-button="{{ ucfirst($action) }}" data-confirm-tone="danger">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="{{ $action }}">
                    <label for="reason-{{ $rider->id }}">Reason (optional, shown to the rider when they sign in)</label>
                    <textarea id="reason-{{ $rider->id }}" name="reason" maxlength="500"></textarea>
                    <button type="submit" class="seller-button seller-button--small seller-button--danger">Confirm {{ $action }}</button>
                </form>
            </div>
        </details>
    @endif

    @if($rider->status === 'suspended')
        <form method="POST" action="{{ route('logistics.riders.update', $rider) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="action" value="reinstate">
            <button type="submit" class="seller-button seller-button--small seller-button--secondary"><i class="fas fa-rotate-left" aria-hidden="true"></i> Reinstate</button>
        </form>
    @endif
</div>
