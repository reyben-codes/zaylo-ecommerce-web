{{-- Status actions for one account. Expects $account (User).
     Reject, suspend and deactivate ask for a reason, which is emailed to the account owner
     (required when rejecting a seller or logistics partner who submitted an application). --}}
@php
    $sellerApplied = $account->role === 'seller' && \Illuminate\Support\Facades\Schema::hasTable('seller_applications') && $account->sellerApplication;
    $partnerApplied = $account->role === 'logistics' && $account->awaitsLogisticsApproval() && $account->logisticsProvider?->hasApplication();
    $explains = $sellerApplied || $partnerApplied;
    // [status, label, icon, button variant, asks for a reason]
    // Rejected accounts have no actions (AdminController::STATUS_CHANGES): the applicant must resubmit first.
    $actions = match ($account->status) {
        'pending' => [['active', 'Approve', 'fa-check', '', false], ['rejected', 'Reject', 'fa-xmark', ' seller-button--danger', true]],
        'active' => [['suspended', 'Suspend', 'fa-ban', ' seller-button--danger', true], ['deactivated', 'Deactivate', 'fa-power-off', ' seller-button--secondary', true]],
        'suspended', 'deactivated' => [['active', 'Reactivate', 'fa-rotate-left', ' seller-button--secondary', false]],
        default => [],
    };
    $reasonHints = [
        'rejected' => 'e.g. The DTI certificate is blurred and the business name cannot be read.',
        'suspended' => 'e.g. Repeated listings of prohibited items after two warnings.',
        'deactivated' => 'e.g. Closed at the owner\'s request.',
    ];
@endphp

@if(! $account->is(auth()->user()))
    <div class="account-actions" data-account-actions>
        <button type="button" class="seller-button seller-button--small seller-button--secondary" data-account-actions-toggle aria-expanded="false" aria-haspopup="dialog" hidden>
            <i class="fas fa-ellipsis" aria-hidden="true"></i> Actions
        </button>
        <div class="account-actions__panel" data-account-actions-panel>
            <div class="account-actions__heading">
                <strong data-account-actions-title>Actions for {{ $account->name }}</strong>
                <button type="button" class="account-actions__close" data-account-actions-close aria-label="Close account actions" hidden><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
@if($account->archived_at !== null)
    <div class="review-actions">
        <form method="POST" action="{{ route('admin.users.restore', $account) }}" data-confirm="Restore {{ $account->name }} with their previous status?" data-confirm-button="Restore">
            @csrf
            @method('PATCH')
            <button class="seller-button seller-button--small seller-button--secondary" type="submit">Restore</button>
        </form>
        <form method="POST" action="{{ route('admin.users.destroy', $account) }}" data-confirm="Are you sure you want to permanently delete {{ $account->name }} and their dependent account data? This cannot be undone. Accounts with protected transaction records must stay archived." data-confirm-title="Delete account permanently?" data-confirm-button="Yes, delete permanently" data-confirm-tone="danger" data-confirm-reason="reason">
            @csrf
            @method('DELETE')
            <input type="hidden" name="reason" value="">
            <button class="seller-button seller-button--small seller-button--danger" type="submit">Delete permanently</button>
        </form>
    </div>
@elseif($account->status === 'rejected')
    <span class="seller-cell-note">Rejected · can be reviewed again once they resubmit their application</span>
@else
    <div class="review-actions">
        @foreach($actions as [$status, $label, $icon, $variant, $asksReason])
            @if($asksReason)
                @php $errorHere = $errors->has('reason') && old('account') == $account->id && old('status') === $status; @endphp
                <details class="review-reason" data-smooth-disclosure @if($errorHere) open @endif>
                    <summary class="seller-button seller-button--small{{ $variant }}"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $label }}</summary>
                    <div data-disclosure-content>
                        <form method="POST" action="{{ route('admin.users.status', $account) }}" class="review-reason__form"
                              data-confirm="{{ ['rejected' => 'Reject', 'suspended' => 'Suspend', 'deactivated' => 'Deactivate'][$status] }} {{ $account->name }}? They will be emailed{{ $status === 'rejected' ? '' : ' and signed out' }}."
                              data-confirm-title="{{ $label }} account?" data-confirm-button="{{ $label }}" @if($status !== 'deactivated') data-confirm-tone="danger" @endif>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="account" value="{{ $account->id }}">
                            <label for="reason-{{ $status }}-{{ $account->id }}">{{ $status === 'rejected' && $explains ? 'Reason shown to the '.($sellerApplied ? 'seller' : 'logistics partner').' (also emailed)' : 'Reason emailed to '.(Str::before($account->name, ' ') ?: 'them').' (optional)' }}</label>
                            <textarea id="reason-{{ $status }}-{{ $account->id }}" name="reason" maxlength="500" @if($status === 'rejected' && $explains) required @endif placeholder="{{ $reasonHints[$status] }}">{{ $errorHere ? old('reason') : '' }}</textarea>
                            @if($errorHere)<p class="seller-field__error" role="alert">{{ $errors->first('reason') }}</p>@endif
                            <button type="submit" class="seller-button seller-button--small{{ $status === 'deactivated' ? ' seller-button--secondary' : ' seller-button--danger' }}">Confirm {{ Str::lower($label) }}</button>
                        </form>
                    </div>
                </details>
                @continue
            @endif
            <form method="POST" action="{{ route('admin.users.status', $account) }}"
                  data-confirm="{{ $label }} {{ $account->name }}? They will be able to sign in and use their account." data-confirm-title="{{ $label }} account?" data-confirm-button="{{ $label }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $status }}">
                <button type="submit" class="seller-button seller-button--small{{ $variant }}"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $label }}</button>
            </form>
        @endforeach
    </div>
@endif

@if(! $account->is(auth()->user()) && $account->archived_at === null)
    <form method="POST" action="{{ route('admin.users.archive', $account) }}" data-confirm="Archive {{ $account->name }}? They will no longer be able to use their account." data-confirm-title="Archive account?" data-confirm-button="Archive">
        @csrf
        @method('PATCH')
        <button class="seller-button seller-button--small seller-button--secondary" type="submit"><i class="fas fa-box-archive" aria-hidden="true"></i> Archive</button>
    </form>
@endif

        </div>
    </div>
@else
    <span class="seller-cell-note">This is you</span>
@endif
