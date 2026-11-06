{{-- Sign-in panel for the shop and the seller, logistics and admin portals. Shown in the sign-in modal
     (partials/auth-modal); behaviour lives in public/js/auth-modal.js. --}}
@php
    $portal ??= 'shop';
    $copy = [
        'shop' => [
            'tagline' => ['Driven by passion.', 'Defined by your origin'], 'label' => 'ZAYLO ACCOUNT', 'heading' => 'Welcome Back',
            'subtitle' => 'Sign in to track orders, save favorites, and shop your everyday essentials.',
            'action' => route('login'), 'button' => 'Sign In',
        ],
        'seller' => [
            'tagline' => ['Build your business.', 'Grow with ZAYLO'], 'label' => 'ZAYLO SELLER CENTER', 'heading' => 'Welcome, Seller',
            'subtitle' => 'Sign in to manage products, inventory, orders, and sales.',
            'action' => route('seller.login.submit'), 'button' => 'Enter Seller Center',
        ],
        'logistics' => [
            'tagline' => ['Deliver with confidence.', 'Move with ZAYLO'], 'label' => 'ZAYLO LOGISTICS CENTER', 'heading' => 'Welcome, Partner',
            'subtitle' => 'Sign in to review rider applications and manage your delivery team.',
            'action' => route('logistics.login.submit'), 'button' => 'Enter Logistics Center',
        ],
        'admin' => [
            'tagline' => ['Keep the marketplace running.', 'Lead with ZAYLO'], 'label' => 'ZAYLO ADMIN CENTER', 'heading' => 'Welcome, Admin',
            'subtitle' => 'Sign in to review applications and manage marketplace accounts.',
            'action' => route('admin.login.submit'), 'button' => 'Enter Admin Center',
        ],
    ][$portal];
@endphp

<div class="auth-panel" data-auth-panel="login" data-tagline="{{ implode('|', $copy['tagline']) }}">
    <header class="auth-panel__header">
        <span class="auth-panel__label">{{ $copy['label'] }}</span>
        <h2 class="auth-panel__title" id="auth-modal-title" tabindex="-1">{{ $copy['heading'] }}</h2>
        <p class="auth-panel__subtitle">{{ $copy['subtitle'] }}</p>
    </header>

    <div class="auth-panel__alerts" data-auth-alerts>
        @if($errors->any())
            <div class="auth-alert auth-alert--error" role="alert">
                {{ $errors->first() }}
                @if(session('portal_hint'))
                    <a href="{{ session('portal_hint.url') }}">{{ session('portal_hint.label') }} &rarr;</a>
                @endif
            </div>
        @endif
        @if(session('error'))<div class="auth-alert auth-alert--error" role="alert">{{ session('error') }}</div>@endif
        @if(session('status'))<div class="auth-alert auth-alert--success" role="status">{{ session('status') }}</div>@endif
    </div>

    <form method="POST" action="{{ $copy['action'] }}" id="loginForm" data-auth-form>
        @csrf
        <input type="hidden" name="return_to" value="" data-return-to>

        <div class="form-group">
            <label for="login_email">Email address</label>
            <input type="email" id="login_email" name="email" value="{{ old('email') }}" placeholder="Enter your email address" autocomplete="username" required autofocus>
        </div>

        <div class="form-group">
            <label for="login_password">Password</label>
            <div class="password-input-wrapper">
                <input type="password" id="login_password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                <button type="button" class="toggle-password" data-password="login_password" aria-controls="login_password" aria-label="Show password" aria-pressed="false">Show</button>
            </div>
        </div>

        <div class="auth-panel__options">
            <label class="remember-me"><input type="checkbox" name="remember" checked> <span>Remember me</span></label>
            <a href="{{ route('password.request') }}" class="auth-panel__link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-primary auth-submit" data-busy-label="Signing in…">{{ $copy['button'] }}</button>
    </form>

    {{-- Google sign-in exists for buyer and seller accounts, each on its own page. --}}
    @if(in_array($portal, ['shop', 'seller'], true))
        <div class="auth-divider"><span>or</span></div>
        <a href="{{ route('google.redirect', $portal === 'seller' ? ['role' => 'seller'] : []) }}" class="btn-google" data-auth-native>
            @include('auth.partials.google-icon')
            <span>Continue with Google</span>
        </a>
    @endif

    <footer class="auth-panel__footer">
        @if($portal === 'seller')
            <p>New to ZAYLO? <a href="{{ route('register', ['role' => 'seller']) }}">Apply to become a seller</a></p>
            <p>Shopping on ZAYLO? <a href="{{ route('login') }}">Use the shop sign in</a></p>
        @elseif($portal === 'admin')
            <p>Not an administrator? <a href="{{ route('login') }}">Use the shop sign in</a></p>
        @elseif($portal === 'logistics')
            <p>New partner? <a href="{{ route('register', ['role' => 'logistics']) }}">Apply to become a logistics partner</a></p>
            <p>Shopping on ZAYLO? <a href="{{ route('login') }}">Use the shop sign in</a></p>
        @else
            <p>Don't have an account? <a href="{{ route('register') }}">Create new account</a></p>
            <div class="auth-panel__portals">
                <p>Selling on ZAYLO? <a href="{{ route('seller.login') }}">Sign in to Seller Center</a></p>
                <p>Delivery partner? <a href="{{ route('logistics.login') }}">Sign in to Logistics Center</a></p>
                <p>Rider? <a href="{{ route('rider.app') }}">Sign in on the ZAYLO app</a></p>
                <p>Administrator? <a href="{{ route('admin.login') }}">Sign in to Admin Center</a></p>
            </div>
        @endif
    </footer>
</div>
