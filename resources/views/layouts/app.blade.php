<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZAYLO</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- Base Styles -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}" />

    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/wishlist-animation.css') }}?v={{ filemtime(public_path('css/wishlist-animation.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/quantity-stepper.css') }}?v={{ filemtime(public_path('css/quantity-stepper.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/product-card-description.css') }}?v={{ filemtime(public_path('css/product-card-description.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/product-rating.css') }}?v={{ filemtime(public_path('css/product-rating.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/category-tags.css') }}?v={{ filemtime(public_path('css/category-tags.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/logout-confirm.css') }}?v={{ filemtime(public_path('css/logout-confirm.css')) }}">
</head>
<body class="@yield('body-class')">
    <!-- Navigation -->
    <header class="navbar">
        <button
            class="nav-menu-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="primary-navigation"
            aria-label="Open navigation menu"
            data-nav-toggle
        >
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </button>

        <div class="nav-menu" id="primary-navigation" data-nav-menu>
            <div class="nav-menu-inner">
                @hasSection('nav-links')
                    @yield('nav-links')
                @else
                    @include('partials.store-nav')
                @endif
            </div>
        </div>

        <a href="{{ route('home') }}" class="logo">
            <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" style="height:72px;width:auto;display:block;">
        </a>

        <div class="nav-actions">
            <form class="search-wrapper" method="GET" action="{{ route('products.index') }}" role="search">
                <span class="search-icon">⌕</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search products" aria-label="Search products" />
            </form>
            <div class="icon-group">
                @if(auth()->user()?->role === 'buyer')
                    @include('partials.buyer-nav-icons')
                @else
                    @yield('nav-icons')
                @endif

                @auth
                <form method="POST" action="{{ route('logout') }}" data-logout-confirm style="display:inline;margin-left:4px;">
                    @csrf
                    @if(auth()->user()->role !== 'buyer')
                    <span style="font-size:0.8rem;font-weight:500;color:#6b5f54;border-left:1px solid #d8d0c8;padding-left:20px;margin-right:10px;">
                        {{ auth()->user()->name }}
                    </span>
                    @endif
                    <button type="submit" class="login-text" style="background:none;border:none;cursor:pointer;font-size:0.8rem;font-weight:500;letter-spacing:0.04em;color:#1e1e1e;border-left:1px solid #d8d0c8;padding-left:16px;">
                        Logout
                    </button>
                </form>
                @else
                    <div class="nav-auth-buttons">
                        <a href="{{ route('login') }}" class="nav-auth-button">Log in</a>
                        <a href="{{ route('register') }}" class="nav-auth-button nav-auth-button--primary">Create new account</a>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    @include('partials.notifications')
    @include('partials.announcements')

    <div class="container">
        <!-- Page Content -->
        @yield('content')
    </div>

    <script src="{{ asset('js/navigation.js') }}?v={{ filemtime(public_path('js/navigation.js')) }}" defer></script>
    <script src="{{ asset('js/notifications.js') }}?v={{ filemtime(public_path('js/notifications.js')) }}" defer></script>
    <script src="{{ asset('js/product-carousel.js') }}?v={{ filemtime(public_path('js/product-carousel.js')) }}" defer></script>
    @stack('scripts')
    @if(auth()->user()?->role === 'buyer')
        <div class="wishlist-feedback" data-wishlist-feedback role="status" aria-live="polite" hidden></div>
        <script src="{{ asset('js/wishlist-animation.js') }}?v={{ filemtime(public_path('js/wishlist-animation.js')) }}" defer></script>
    @endif
    <script src="{{ asset('js/quantity-stepper.js') }}?v={{ filemtime(public_path('js/quantity-stepper.js')) }}" defer></script>
    @include('partials.chat-widget')
    @include('partials.cart-animation')
    <script src="{{ asset('js/logout-confirm.js') }}?v={{ filemtime(public_path('js/logout-confirm.js')) }}" defer></script>
</body>
</html>
