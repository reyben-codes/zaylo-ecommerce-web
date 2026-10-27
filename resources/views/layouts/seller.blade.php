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
    @include('partials.theme-head')
    @foreach(['seller-sidebar', 'seller-header', 'seller-workspace'] as $stylesheet)
        <link rel="stylesheet" href="{{ asset("css/{$stylesheet}.css") }}?v={{ filemtime(public_path("css/{$stylesheet}.css")) }}">
    @endforeach
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/confirm-dialog.css') }}?v={{ filemtime(public_path('css/confirm-dialog.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}?v={{ filemtime(public_path('css/print.css')) }}" media="print">
</head>
<body class="seller-workspace" @if(auth()->user()?->hasRole('seller') && auth()->user()->isActive()) data-seller-updates-url="{{ route('seller.updates') }}" @endif>
    @include('partials.seller-header')
    @include('partials.notifications')
    @include('partials.announcements')

    <div class="seller-shell">
        @include('partials.seller-sidebar')

        <main class="seller-main" id="main-content" @hasSection('auto-refresh') data-auto-refresh @endif>
            @include('seller.partials.alert-banner')
            <p class="seller-live-update" data-live-update role="status" hidden>
                <i class="fas fa-rotate" aria-hidden="true"></i>
                <span>There are new updates on this page.</span>
                <button type="button" class="seller-button seller-button--small">Refresh</button>
            </p>
            @yield('content')
        </main>
    </div>

    @stack('dialogs')

    <script src="{{ asset('js/notifications.js') }}?v={{ filemtime(public_path('js/notifications.js')) }}" defer></script>
    <script src="{{ asset('js/smooth-disclosure.js') }}?v={{ filemtime(public_path('js/smooth-disclosure.js')) }}" defer></script>
    <script src="{{ asset('js/seller-workspace.js') }}?v={{ filemtime(public_path('js/seller-workspace.js')) }}" defer></script>
    <script src="{{ asset('js/seller-live-updates.js') }}?v={{ filemtime(public_path('js/seller-live-updates.js')) }}" defer></script>
    @stack('scripts')
    <script src="{{ asset('js/confirm-dialog.js') }}?v={{ filemtime(public_path('js/confirm-dialog.js')) }}" defer></script>
    <script src="{{ asset('js/print.js') }}?v={{ filemtime(public_path('js/print.js')) }}" defer></script>
    @include('partials.faq-bot')
</body>
</html>
