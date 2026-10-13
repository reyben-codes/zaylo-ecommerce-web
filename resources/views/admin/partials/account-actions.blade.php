{{-- Status actions for one account. Expects $account (User). Rejecting a seller asks for the reason they will see. --}}
@php
    $actions = match ($account->status) {
        'pending' => [['active', 'Approve', 'fa-check', '', null], ['rejected', 'Reject', 'fa-xmark', ' seller-button--danger', "Reject {$account->name}'s application? They will not be able to sign in."]],
        'active' => [['suspended', 'Suspend', 'fa-ban', ' seller-button--danger', "Suspend {$account->name}? They will be signed out and blocked until reactivated."]],
        'suspended' => [['active', 'Reactivate', 'fa-rotate-left', ' seller-button--secondary', null]],
        'rejected' => [['active', 'Approve', 'fa-check', ' seller-button--secondary', null]],
        default => [],
    };
    $asksReason = $account->role === 'seller' && \Illuminate\Support\Facades\Schema::hasTable('seller_applications') && $account->sellerApplication;
@endphp

@if($account->is(auth()->user()))
    <span class="seller-cell-note">This is you</span>
@else
    <div class="review-actions">
        @foreach($actions as [$status, $label, $icon, $variant, $confirm])
            @if($status === 'rejected' && $asksReason)
                <details class="review-reason" data-smooth-disclosure @if($errors->has('reason') && old('account') == $account->id) open @endif>
                    <summary class="seller-button seller-button--small{{ $variant }}"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $label }}</summary>
                    <div data-disclosure-content>
                        <form method="POST" action="{{ route('admin.users.status', $account) }}" class="review-reason__form">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="rejected">
                            <input type="hidden" name="account" value="{{ $account->id }}">
                            <label for="reject-reason-{{ $account->id }}">Reason shown to the seller</label>
                            <textarea id="reject-reason-{{ $account->id }}" name="reason" maxlength="500" required placeholder="e.g. The DTI certificate is blurred and the business name cannot be read.">{{ old('account') == $account->id ? old('reason') : '' }}</textarea>
                            @if($errors->has('reason') && old('account') == $account->id)<p class="seller-field__error" role="alert">{{ $errors->first('reason') }}</p>@endif
                            <button type="submit" class="seller-button seller-button--small seller-button--danger">Confirm rejection</button>
                        </form>
                    </div>
                </details>
                @continue
            @endif
            <form method="POST" action="{{ route('admin.users.status', $account) }}" @if($confirm) data-confirm="{{ $confirm }}" @endif>
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $status }}">
                <button type="submit" class="seller-button seller-button--small{{ $variant }}"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $label }}</button>
            </form>
        @endforeach
    </div>
@endif
