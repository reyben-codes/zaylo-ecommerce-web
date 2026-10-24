{{-- Status actions for one account. Expects $account (User).
     Reject, suspend and deactivate ask for a reason, which is emailed to the account owner
     (required when rejecting a seller or logistics partner who submitted an application). --}}
@php
    $sellerApplied = $account->role === 'seller' && \Illuminate\Support\Facades\Schema::hasTable('seller_applications') && $account->sellerApplication;
    $partnerApplied = $account->role === 'logistics' && $account->awaitsLogisticsApproval() && $account->logisticsProvider?->hasApplication();
    $explains = $sellerApplied || $partnerApplied;
    // [status, label, icon, button variant, asks for a reason]
    $actions = match ($account->status) {
        'pending' => [['active', 'Approve', 'fa-check', '', false], ['rejected', 'Reject', 'fa-xmark', ' seller-button--danger', true]],
        'active' => [['suspended', 'Suspend', 'fa-ban', ' seller-button--danger', true], ['deactivated', 'Deactivate', 'fa-power-off', ' seller-button--secondary', true]],
        'suspended', 'deactivated' => [['active', 'Reactivate', 'fa-rotate-left', ' seller-button--secondary', false]],
        'rejected' => [['active', 'Approve', 'fa-check', ' seller-button--secondary', false]],
        default => [],
    };
    $reasonHints = [
        'rejected' => 'e.g. The DTI certificate is blurred and the business name cannot be read.',
        'suspended' => 'e.g. Repeated listings of prohibited items after two warnings.',
        'deactivated' => 'e.g. Closed at the owner\'s request.',
    ];
@endphp

@if($account->is(auth()->user()))
    <span class="seller-cell-note">This is you</span>
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
