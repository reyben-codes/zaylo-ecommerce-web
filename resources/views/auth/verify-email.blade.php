@extends('layouts.auth')
@section('title', 'ZAYLO · Verify your email')
@include('auth.partials.registration-styles')

@section('content')
<main class="auth-container">
    <aside class="auth-image" aria-label="ZAYLO marketplace">
        <div class="auth-image-content">
            <a class="auth-brand" href="{{ route('home') }}"><img src="{{ asset('images/ZAYLO_LOGO_LIGHT.png') }}" alt="ZAYLO home" data-theme-fixed></a>
            <div class="auth-image-text"><p>Driven by passion.</p><p>Defined by your origin</p></div>
        </div>
    </aside>
    <section class="auth-form" aria-labelledby="verification-title">
        <a href="{{ route('products.index') }}" class="continue-shopping"><span aria-hidden="true">&larr;</span> Continue shopping</a>
        <div class="auth-form-content">
            <header class="auth-header">
                <span class="auth-label">ONE LAST STEP</span>
                <h1 id="verification-title">Verify your email</h1>
                <p class="auth-subtitle">Enter the six-digit code sent to <span class="email-address">{{ $email }}</span>.</p>
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

            <form method="POST" action="{{ route('verification.send') }}" class="resend-form">
                @csrf
                <p class="field-note">Didn't receive a code? Check your spam folder or request another. Please wait one minute between requests.</p>
                <button type="submit" class="btn-back">Resend code</button>
            </form>

            <footer class="auth-footer">
                @if($pending)
                    <p>Wrong email address?</p>
                    <form method="POST" action="{{ route('registration.cancel') }}" data-confirm="Your sign-up details will be discarded and you will need to fill in the form again." data-confirm-title="Start over?" data-confirm-button="Start over">@csrf<button type="submit" class="btn-back">Start over</button></form>
                @else
                    <p>Using the wrong account?</p>
                    <form method="POST" action="{{ route('logout') }}" data-logout-confirm>@csrf<button type="submit" class="btn-back">Sign out</button></form>
                @endif
            </footer>
        </div>
    </section>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/email-verification.css') }}?v={{ filemtime(public_path('css/email-verification.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/email-verification.js') }}?v={{ filemtime(public_path('js/email-verification.js')) }}" defer></script>
@endpush
