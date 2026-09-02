<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ZAYLO · Quiet Luxury')</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- Base Styles -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}" />

    @stack('styles')
</head>
<body>
    <!-- Navigation -->
    <header class="navbar">
        @yield('nav-links')

        <a href="{{ route('home') }}" class="logo">
            <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" style="height:72px;width:auto;display:block;">
        </a>

        <div class="nav-actions">
            <div class="search-wrapper">
                <span class="search-icon">⌕</span>
                <input type="text" placeholder="Search" />
            </div>
            <div class="icon-group">
                @yield('nav-icons')

                <form method="POST" action="{{ route('logout') }}" style="display:inline;margin-left:4px;">
                    @csrf
                    <span style="font-size:0.8rem;font-weight:500;color:#6b5f54;border-left:1px solid #d8d0c8;padding-left:20px;margin-right:10px;">
                        {{ auth()->user()->name }}
                    </span>
                    <button type="submit" class="login-text" style="background:none;border:none;cursor:pointer;font-size:0.8rem;font-weight:500;letter-spacing:0.04em;color:#1e1e1e;border-left:1px solid #d8d0c8;padding-left:16px;">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Page Content -->
        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
