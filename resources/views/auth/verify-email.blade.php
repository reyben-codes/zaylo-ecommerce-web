@extends('layouts.auth')
@section('title', 'ZAYLO · Verify your email')
@include('auth.partials.registration-styles')

@section('content')
<main class="auth-container">
    <aside class="auth-image" aria-label="ZAYLO marketplace">
        <div class="auth-image-content">
            <a class="auth-brand" href="{{ route('home') }}"><img src="{{ asset('images/ZAYLO_LOGO_LIGHT.png') }}" alt="ZAYLO home"></a>
            <div class="auth-image-text"><p>Everything you need,</p><p>all in one place.</p></div>
        </div>
    </aside>
    <section class="auth-form" aria-labelledby="verification-title">
        <a href="{{ route('products.index') }}" class="continue-shopping"><span aria-hidden="true">&larr;</span> Continue shopping</a>
        <div class="auth-form-content">
            <header class="auth-header">
                <span class="auth-label">ONE LAST STEP</span>
                <h1 id="verification-title">Verify your email</h1>
                <p class="auth-subtitle">Enter the six-digit code sent to <span class="email-address">{{ auth()->user()->email }}</span>.</p>
            </header>

            @include('auth.partials.registration-progress', ['activeStep' => 3])

            @if(session('status'))<p class="alert-status" role="status">{{ session('status') }}</p>@endif
            @if($errors->any())
                <div class="alert-error" id="verification-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('verification.verify') }}" id="verificationForm">
                @csrf
                <div class="form-group">
                    <label for="otp">Email verification code</label>
                    <input class="otp-input" id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="000000" required aria-describedby="otp-note{{ $errors->has('otp') ? ' verification-error' : '' }}" @if($errors->has('otp')) aria-invalid="true" @endif>
                    <p class="field-note" id="otp-note">Your code expires after 10 minutes. You can paste all six digits here.</p>
                </div>
                @if(auth()->user()->role !== 'buyer')
                    <p class="partner-note">After email verification, your {{ auth()->user()->role }} application will still need administrator approval.</p>
                @endif
                <div class="step-actions"><button type="submit" class="btn-primary" id="verify-button">Verify email &amp; {{ auth()->user()->isActive() ? 'continue' : 'finish application' }}</button></div>
            </form>

            <form method="POST" action="{{ route('verification.send') }}" class="resend-form">
                @csrf
                <p class="field-note">Didn't receive a code? Check your spam folder or request another. Please wait one minute between requests.</p>
                <button type="submit" class="btn-back">Resend code</button>
            </form>

            <footer class="auth-footer">
                <p>Using the wrong account?</p>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn-back">Sign out</button></form>
            </footer>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('verificationForm');
    const button = document.getElementById('verify-button');
    const label = button.textContent;
    form.addEventListener('submit', () => {
        button.disabled = true;
        button.textContent = 'Verifying…';
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
        button.textContent = label;
    });
})();
</script>
@endpush
