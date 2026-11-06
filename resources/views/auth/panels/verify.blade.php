{{-- Last sign-up step, shown in the sign-in modal: the six-digit email code. Code entry lives in
     public/js/email-verification.js; Resend reloads this panel through public/js/auth-modal.js. --}}
<div class="auth-panel" data-auth-panel="verify">
    <header class="auth-panel__header">
        <span class="auth-panel__label">ONE LAST STEP</span>
        <h2 class="auth-panel__title" id="auth-modal-title" tabindex="-1">Verify your email</h2>
        <p class="auth-panel__subtitle">Enter the six-digit code sent to <span class="email-address">{{ $email }}</span>.</p>
    </header>

    <div class="auth-wizard__progress auth-wizard__progress--final">
        <p class="auth-wizard__count">Final step</p>
        <ol class="auth-wizard__bar" aria-hidden="true"><li class="is-done"></li><li class="is-done"></li><li class="is-current"></li></ol>
    </div>

    <div class="auth-panel__alerts" data-auth-alerts>
        @if(session('status'))<p class="auth-alert auth-alert--success" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())
            <div class="auth-alert auth-alert--error" id="verification-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
    </div>

    <form method="POST" action="{{ route('verification.verify') }}" id="verificationForm">
        @csrf
        <div class="form-group">
            <label for="otp">Email verification code</label>
            <div class="otp-field" data-otp-field data-state="idle">
                <input class="otp-input" id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="000000" required autofocus aria-describedby="otp-status otp-note{{ $errors->has('otp') ? ' verification-error' : '' }}" @if($errors->has('otp')) aria-invalid="true" @endif>
                <div class="otp-slots" aria-hidden="true">
                    @for($i = 0; $i < 6; $i++)<span class="otp-slot"><span class="otp-digit"></span></span>@endfor
                </div>
            </div>
            <p class="otp-status" id="otp-status" role="status" aria-live="polite"></p>
            <div class="otp-tools">
                <button type="button" class="otp-paste" data-otp-paste hidden><i class="fa-regular fa-clipboard" aria-hidden="true"></i> Paste code</button>
            </div>
            <p class="field-note" id="otp-note">Your code expires after 5 minutes. It checks itself once all six digits are in — type, paste, or pick the code your phone suggests.</p>
        </div>
        @if($pending)
            <p class="field-note">Your account is created once you enter this code. Until then nothing is saved, and unfinished sign-ups are removed after 24 hours.</p>
        @endif
        @if($role !== 'buyer')
            <p class="partner-note">After email verification, your {{ $role }} application will still need {{ $role === 'rider' ? 'your logistics partner' : 'administrator' }} approval.</p>
        @endif
        <div class="step-actions"><button type="submit" class="btn-primary" id="verify-button">Verify email &amp; {{ $pending ? 'create account' : ($active ? 'continue' : 'finish application') }}</button></div>
    </form>

    <form method="POST" action="{{ route('verification.send') }}" class="resend-form" data-auth-form data-auth-reload>
        @csrf
        <p class="field-note">Didn't receive a code? Check your spam folder or request another. Please wait one minute between requests.</p>
        <button type="submit" class="btn-back" data-busy-label="Sending…">Resend code</button>
    </form>

    <footer class="auth-panel__footer">
        @if($pending)
            <p>Wrong email address?</p>
            <form method="POST" action="{{ route('registration.cancel') }}" data-auth-native data-confirm="Your sign-up details will be discarded and you will need to fill in the form again." data-confirm-title="Start over?" data-confirm-button="Start over">@csrf<button type="submit" class="btn-back">Start over</button></form>
        @else
            <p>Using the wrong account?</p>
            <form method="POST" action="{{ route('logout') }}" data-auth-native data-logout-confirm>@csrf<button type="submit" class="btn-back">Sign out</button></form>
        @endif
    </footer>
</div>
