{{-- Forgot-password panel, shown in the sign-in modal. The reset link in the email opens its own page. --}}
<div class="auth-panel" data-auth-panel="forgot-password">
    <header class="auth-panel__header">
        <span class="auth-panel__label">ZAYLO ACCOUNT</span>
        <h2 class="auth-panel__title" id="auth-modal-title" tabindex="-1">Forgot your password?</h2>
        <p class="auth-panel__subtitle">Enter your account email and we’ll send a secure reset link.</p>
    </header>

    <div class="auth-panel__alerts" data-auth-alerts>
        @if(session('status'))<p class="auth-alert auth-alert--success" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<p class="auth-alert auth-alert--error" role="alert">{{ $errors->first() }}</p>@endif
    </div>

    <form method="POST" action="{{ route('password.email') }}" data-auth-form data-auth-reload>
        @csrf
        <div class="form-group">
            <label for="reset_email">Email address</label>
            <input type="email" id="reset_email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        </div>
        <button type="submit" class="btn-primary auth-submit" data-busy-label="Sending…">Send reset link</button>
    </form>

    <footer class="auth-panel__footer">
        <p>Remembered it? <a href="{{ route('login') }}">Back to sign in</a></p>
    </footer>
</div>
