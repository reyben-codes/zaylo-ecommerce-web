<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | ZAYLO Seller Center</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @foreach(['seller-sidebar', 'seller-header', 'seller-workspace'] as $stylesheet)
        <link rel="stylesheet" href="{{ asset("css/{$stylesheet}.css") }}?v={{ filemtime(public_path("css/{$stylesheet}.css")) }}">
    @endforeach
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/logout-confirm.css') }}?v={{ filemtime(public_path('css/logout-confirm.css')) }}">
    @include('partials.theme-head')
</head>
<body class="seller-workspace">
    @include('partials.seller-header')
    @include('partials.notifications')
    @include('partials.announcements')

    <div class="seller-shell">
        @include('partials.seller-sidebar')

        <main class="seller-main" id="main-content">
            @yield('content')
        </main>
    </div>

    @stack('dialogs')

    <script src="{{ asset('js/notifications.js') }}?v={{ filemtime(public_path('js/notifications.js')) }}" defer></script>
    <script src="{{ asset('js/smooth-disclosure.js') }}?v={{ filemtime(public_path('js/smooth-disclosure.js')) }}" defer></script>
    <script src="{{ asset('js/seller-workspace.js') }}?v={{ filemtime(public_path('js/seller-workspace.js')) }}" defer></script>
    @stack('scripts')
    <script src="{{ asset('js/logout-confirm.js') }}?v={{ filemtime(public_path('js/logout-confirm.js')) }}" defer></script>
</body>
</html>
