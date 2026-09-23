@extends('layouts.auth')

@php($sellerPortal = $sellerPortal ?? false)

@section('title', $sellerPortal ? 'ZAYLO · Seller Sign In' : 'ZAYLO · Log In')

@push('styles')
<style>
    :root {
        --color-primary: #1a1714;
        --color-secondary: #b28b6f;
        --color-cream: #faf7f2;
        --color-off-white: #f5f0ea;
        --color-dark: #0a0a0a;
        --color-gray: #6b5f54;
        --color-light-gray: #e5dfd8;
        --color-border: #ece4db;
        --color-error: #c0392b;
        --color-success: #2d7d46;
        --font-serif: 'Playfair Display', 'Times New Roman', serif;
        --font-sans: 'Inter', 'Helvetica Neue', sans-serif;
        --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: var(--font-sans); background: var(--color-cream); color: var(--color-primary); }
    .auth-container { display: flex; min-height: 100vh; min-height: 100svh; width: 100%; background: white; }
    .auth-image { flex: 0 0 50%; min-height: 720px; position: relative; overflow: hidden; background: #39291d url('{{ asset('images/login-marketplace.png') }}') center/cover no-repeat; border-radius: 0 24px 24px 0; }
    .auth-image-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, transparent 60%, rgba(26, 23, 20, 0.7)); }
    .auth-image-content { position: absolute; top: 0; left: 0; width: 100%; height: 100%; padding: 40px; display: flex; flex-direction: column; justify-content: space-between; z-index: 2; }
    .auth-brand { display: flex; align-items: center; gap: 16px; }
    .auth-brand-line { width: 40px; height: 2px; background: white; opacity: 0.6; }
    .auth-image-text { color: white; }
    .auth-image-text p { font-family: var(--font-serif); font-size: 1.6rem; font-weight: 300; line-height: 1.4; opacity: 0.9; }
    .auth-image-text p:last-child { font-style: italic; opacity: 0.7; }
    .auth-form { flex: 1; min-width: 0; padding: 100px 64px 64px; display: flex; flex-direction: column; justify-content: center; background: white; position: relative; }
    .continue-shopping { position: absolute; top: 28px; left: 64px; display: inline-flex; align-items: center; gap: 8px; min-height: 44px; color: var(--color-gray); text-decoration: none; font-size: 0.82rem; }
    .continue-shopping:hover { color: var(--color-primary); text-decoration: underline; }
    a:focus-visible, button:focus-visible { outline: 2px solid var(--color-secondary); outline-offset: 4px; }
    .auth-form-content { width: 100%; max-width: 520px; margin: 0 auto; }
    .auth-header { margin-bottom: 36px; }
    .auth-label { font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: var(--color-gray); font-weight: 400; display: block; margin-bottom: 10px; }
    .auth-header h1 { font-family: var(--font-serif); font-size: 2.8rem; font-weight: 600; color: var(--color-primary); margin-bottom: 6px; letter-spacing: -0.01em; }
    .auth-subtitle { font-size: 0.95rem; color: var(--color-gray); font-weight: 400; line-height: 1.65; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 0.75rem; font-weight: 500; color: var(--color-primary); margin-bottom: 8px; letter-spacing: 0.02em; }
    .form-group input { width: 100%; padding: 14px 18px; border: 1px solid var(--color-border); border-radius: 10px; font-size: 0.95rem; font-family: var(--font-sans); transition: var(--transition); outline: none; background: white; color: var(--color-primary); }
    .form-group input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(26, 23, 20, 0.05); }
    .form-group input::placeholder { color: #c5c0b8; }
    .form-group input.error { border-color: var(--color-error); }
    .password-input-wrapper { position: relative; }
    .password-input-wrapper input { padding-right: 48px; }
    .toggle-password { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-gray); cursor: pointer; font: inherit; font-size: 0.75rem; min-width: 44px; min-height: 44px; padding: 4px; transition: var(--transition); }
    .toggle-password:hover { color: var(--color-primary); }
    .form-options { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    .remember-me { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--color-gray); cursor: pointer; }
    .remember-me input[type="checkbox"] { accent-color: var(--color-primary); width: 16px; height: 16px; cursor: pointer; }
    .forgot-password { font-size: 0.78rem; color: var(--color-gray); text-decoration: none; transition: var(--transition); }
    .forgot-password:hover { color: var(--color-primary); }
    .btn-auth-primary { width: 100%; padding: 16px; background: var(--color-primary); color: white; border: none; border-radius: 10px; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; cursor: pointer; transition: var(--transition); font-family: var(--font-sans); }
    .btn-auth-primary:hover { background: var(--color-secondary); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
    .auth-divider { text-align: center; margin: 24px 0; position: relative; }
    .auth-divider::before { content: ''; position: absolute; left: 0; top: 50%; width: 100%; height: 1px; background: var(--color-border); }
    .auth-divider span { background: white; padding: 0 18px; font-size: 0.7rem; color: var(--color-gray); position: relative; text-transform: uppercase; letter-spacing: 0.04em; }
    .social-login { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 28px; }
    .btn-social { padding: 13px; background: white; border: 1px solid var(--color-border); border-radius: 10px; font-size: 0.8rem; font-weight: 500; font-family: var(--font-sans); cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 10px; color: var(--color-primary); }
    .btn-social:hover { border-color: var(--color-primary); background: var(--color-cream); }
    .btn-social i { font-size: 1.05rem; }
    .btn-social .google-icon { width: 18px; height: 18px; flex-shrink: 0; }
    a.btn-social { text-decoration: none; }
    .auth-footer { margin-top: 28px; padding-top: 24px; border-top: 1px solid var(--color-border); text-align: center; font-size: 0.82rem; color: var(--color-gray); line-height: 1.6; }
    .auth-footer a { color: var(--color-primary); text-decoration: none; font-weight: 500; transition: var(--transition); }
    .auth-footer a:hover { color: var(--color-secondary); text-decoration: underline; }
    .alert-error { background: #fdf2f2; border: 1px solid #f5c6c6; color: var(--color-error); padding: 12px 16px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 20px; }
    @media (max-width: 820px) {
        .auth-container { flex-direction: column; }
        .auth-image { flex: none; min-height: 260px; border-radius: 0 0 24px 24px; background-size: 55% auto; background-position: right 65%; }
        .auth-image-overlay { background: linear-gradient(90deg, #39291d 35%, transparent 85%); }
        .auth-image-content { padding: 28px; }
        .auth-image-text { max-width: 55%; }
        .auth-image-text p { font-size: 1.4rem; }
        .auth-form { padding: 84px 28px 40px; }
        .continue-shopping { top: 20px; left: 28px; }
    }
    @media (max-width: 480px) {
        .auth-image { min-height: 220px; }
        .auth-image-content { padding: 24px; }
        .auth-image-text p { font-size: 1.15rem; }
        .auth-brand-line { display: none; }
        .auth-form { padding: 80px 24px 36px; }
        .continue-shopping { left: 24px; }
        .auth-header { margin-bottom: 28px; }
        .auth-header h1 { font-size: 2rem; }
        .form-options { gap: 12px; flex-wrap: wrap; }
    }
</style>
@endpush

@section('content')
<div class="auth-container{{ $sellerPortal ? ' seller-auth' : '' }}">
    <!-- Left Side - Image -->
    <div class="auth-image">
        <div class="auth-image-content">
            <div class="auth-brand">
                <span class="auth-brand-line"></span>
                <span class="auth-brand-name">
                    <img src="{{ asset('images/ZAYLO_LOGO_LIGHT.png') }}" alt="ZAYLO" style="height:36px;width:auto;display:block;">
                </span>
            </div>
            <div class="auth-image-text">
                <p>{{ $sellerPortal ? 'Build your business.' : 'Driven by passion.' }}</p>
                <p>{{ $sellerPortal ? 'Grow with ZAYLO' : 'Defined by your origin' }}</p>
            </div>
        </div>
        <div class="auth-image-overlay"></div>
    </div>

    <!-- Right Side - Form -->
    <div class="auth-form">
        <a href="{{ $sellerPortal ? route('home') : route('products.index') }}" class="continue-shopping">
            <span aria-hidden="true">&larr;</span> {{ $sellerPortal ? 'Back to ZAYLO' : 'Continue shopping' }}
        </a>

        <div class="auth-form-content">
            <div class="auth-header">
                <span class="auth-label">{{ $sellerPortal ? 'ZAYLO SELLER CENTER' : 'ZAYLO ACCOUNT' }}</span>
                <h1 id="welcomeHeading">{{ $sellerPortal ? 'Welcome, Seller' : 'Welcome Back' }}</h1>
                <p class="auth-subtitle">{{ $sellerPortal ? 'Sign in to manage products, inventory, orders, and sales.' : 'Sign in to track orders, save favorites, and shop your everyday essentials.' }}</p>
            </div>

            @if ($errors->any())
                <div class="alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert-error">
                    {{ session('error') }}
                </div>
            @endif

            @if (session('status'))
                <div class="alert-error" style="color:#2d7d46;background:#f1faf4;border-color:#b8dfc4;">{{ session('status') }}</div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ $sellerPortal ? route('seller.login.submit') : route('login') }}" id="loginForm">
                @csrf

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           placeholder="Enter your email address" autocomplete="username" required />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" autocomplete="current-password" required />
                        <button type="button" class="toggle-password" aria-label="Show password" aria-controls="password" aria-pressed="false" onclick="togglePassword('password', this)">
                            Show
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" checked />
                        <span>Remember me</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-auth-primary">{{ $sellerPortal ? 'Enter Seller Center' : 'Sign In' }}</button>
            </form>

            @unless($sellerPortal)
            <div class="auth-divider"><span>or</span></div>
            <div class="social-login">
                <a href="{{ route('google.redirect') }}" class="btn-social btn-google">
                    <svg
                        class="google-icon"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            fill="#4285F4"
                            d="M21.35 12.27c0-.79-.07-1.55-.2-2.27H12v4.3h5.23a4.47 4.47 0 0 1-1.94 2.93v2.79h3.14c1.84-1.69 2.92-4.18 2.92-7.75z"
                        />

                        <path
                            fill="#34A853"
                            d="M12 21.75c2.63 0 4.84-.87 6.45-2.36l-3.14-2.43c-.87.58-1.98.93-3.31.93-2.54 0-4.69-1.72-5.46-4.02H3.3v2.51A9.75 9.75 0 0 0 12 21.75z"
                        />

                        <path
                            fill="#FBBC05"
                            d="M6.54 13.87A5.86 5.86 0 0 1 6.23 12c0-.65.11-1.28.31-1.87V7.62H3.3A9.75 9.75 0 0 0 2.25 12c0 1.57.38 3.05 1.05 4.38l3.24-2.51z"
                        />

                        <path
                            fill="#EA4335"
                            d="M12 6.11c1.43 0 2.72.49 3.73 1.46l2.79-2.79C16.83 3.21 14.63 2.25 12 2.25a9.75 9.75 0 0 0-8.7 5.37l3.24 2.51c.77-2.3 2.92-4.02 5.46-4.02z"
                        />

                    </svg>
                    Continue with Google
                </a>
            </div>
            @endunless

            <div class="auth-footer">
                @if($sellerPortal)
                    <p>New to ZAYLO? <a href="{{ route('register', ['role' => 'seller']) }}">Apply to become a seller</a></p>
                    <p style="margin-top:8px;">Shopping on ZAYLO? <a href="{{ route('login') }}">Use the shop sign in</a></p>
                @else
                    <p>Don't have an account? <a href="{{ route('register') }}">Create new account</a></p>
                    <p style="margin-top:8px;">Selling on ZAYLO? <a href="{{ route('seller.login') }}">Sign in to Seller Center</a></p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        button.textContent = showPassword ? 'Hide' : 'Show';
        button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(showPassword));
    }
</script>
@endpush
