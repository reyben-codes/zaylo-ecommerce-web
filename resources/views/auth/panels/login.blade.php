{{-- Sign-in panel for every role, shown in the sign-in modal (partials/auth-modal). Role cards at the top pick the
     account type: public/js/auth-modal.js switches the form in place from each card's data-portal, keeping what was
     typed; without JavaScript each card is a link to that role's sign-in page. Couriers sign in here or in the app;
     riders sign in only in the app. --}}
@php
    $portal ??= 'shop';
    $portals = [
        'shop' => [
            'name' => 'Buyer', 'icon' => 'fa-bag-shopping', 'url' => route('login'),
            'label' => 'ZAYLO ACCOUNT', 'heading' => 'Welcome Back',
            'subtitle' => 'Sign in to track orders, save favorites, and shop your everyday essentials.',
            'action' => route('login'), 'button' => 'Sign In',
            'google' => route('google.redirect'),
            'footer' => ["Don't have an account?", 'Create new account', route('register')],
        ],
        'seller' => [
            'name' => 'Seller', 'icon' => 'fa-store', 'url' => route('seller.login'),
            'label' => 'ZAYLO SELLER CENTER', 'heading' => 'Welcome, Seller',
            'subtitle' => 'Sign in to manage products, inventory, orders, and sales.',
            'action' => route('seller.login.submit'), 'button' => 'Enter Seller Center',
            'google' => route('google.redirect', ['role' => 'seller']),
            'footer' => ['New to ZAYLO?', 'Apply to become a seller', route('register', ['role' => 'seller'])],
        ],
        'logistics' => [
            'name' => 'Logistics', 'icon' => 'fa-truck-fast', 'url' => route('logistics.login'),
            'label' => 'ZAYLO LOGISTICS CENTER', 'heading' => 'Welcome, Partner',
            'subtitle' => 'Sign in to review rider applications and manage your delivery team.',
            'action' => route('logistics.login.submit'), 'button' => 'Enter Logistics Center',
            'google' => null,
            'footer' => ['New partner?', 'Apply to become a logistics partner', route('register', ['role' => 'logistics'])],
        ],
        'courier' => [
            'name' => 'Courier', 'icon' => 'fa-truck-pickup', 'url' => route('courier.login'),
            'label' => 'ZAYLO COURIER', 'heading' => 'Welcome, Courier',
            'subtitle' => 'Sign in to see the parcels to pick up from sellers and bring to your partner hub.',
            'action' => route('courier.login.submit'), 'button' => 'Sign In as Courier',
            'google' => route('google.redirect', ['role' => 'courier']),
            'footer' => ['New courier?', 'Apply to become a courier', route('register', ['role' => 'courier'])],
        ],
        'admin' => [
            'name' => 'Admin', 'icon' => 'fa-user-shield', 'url' => route('admin.login'),
            'label' => 'ZAYLO ADMIN CENTER', 'heading' => 'Welcome, Admin',
            'subtitle' => 'Sign in to review applications and manage marketplace accounts.',
            'action' => route('admin.login.submit'), 'button' => 'Enter Admin Center',
            'google' => null,
            'footer' => null,
        ],
    ];
    $copy = $portals[$portal];
@endphp

<div class="auth-panel" data-auth-panel="login">
    <nav class="portal-cards" aria-labelledby="portal-cards-label">
        <p class="portal-cards__label" id="portal-cards-label">Sign in as</p>
        <div class="portal-cards__grid">
            @foreach(['shop', 'seller', 'logistics', 'courier'] as $key)
                @include('auth.partials.portal-card', ['key' => $key, 'card' => $portals[$key], 'active' => $portal === $key])
            @endforeach
            {{-- Riders deliver with the app, so this card opens the app download page. --}}
            <a href="{{ route('rider.app') }}" class="portal-card portal-card--app">
                <span class="portal-card__icon" aria-hidden="true"><i class="fas fa-motorcycle"></i></span>
                <span class="portal-card__name">Rider</span>
                <small class="portal-card__note">In the app</small>
            </a>
            @include('auth.partials.portal-card', ['key' => 'admin', 'card' => $portals['admin'], 'active' => $portal === 'admin'])
        </div>
        <p class="sr-only" role="status" aria-live="polite" data-portal-status></p>
    </nav>

    <header class="auth-panel__header" data-portal-header>
        <span class="auth-panel__label" data-portal-text="label">{{ $copy['label'] }}</span>
        <h2 class="auth-panel__title" id="auth-modal-title" tabindex="-1" data-portal-text="heading">{{ $copy['heading'] }}</h2>
        <p class="auth-panel__subtitle" data-portal-text="subtitle">{{ $copy['subtitle'] }}</p>
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

    <form method="POST" action="{{ $copy['action'] }}" id="loginForm" data-auth-form data-portal-form>
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

        <button type="submit" class="btn-primary auth-submit" data-busy-label="Signing in…" data-portal-text="button">{{ $copy['button'] }}</button>
    </form>

    {{-- Google sign-in exists for buyer and seller accounts. --}}
    <div class="portal-google" data-portal-google @unless($copy['google']) hidden @endunless>
        <div class="auth-divider"><span>or</span></div>
        <a href="{{ $copy['google'] ?? route('google.redirect') }}" class="btn-google" data-auth-native>
            @include('auth.partials.google-icon')
            <span>Continue with Google</span>
        </a>
    </div>

    <footer class="auth-panel__footer" data-portal-footer @unless($copy['footer']) hidden @endunless>
        <p><span data-portal-footer-text>{{ $copy['footer'][0] ?? '' }}</span> <a href="{{ $copy['footer'][2] ?? route('register') }}" data-portal-footer-link>{{ $copy['footer'][1] ?? '' }}</a></p>
    </footer>
</div>
