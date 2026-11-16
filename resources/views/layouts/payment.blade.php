<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Payment · ZAYLO')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @include('partials.theme-head')
    <link rel="stylesheet" href="{{ asset('css/payment.css') }}?v={{ filemtime(public_path('css/payment.css')) }}">
</head>
<body class="payment-page">
    <header class="payment-header">
        <a class="payment-logo" href="{{ url('/') }}" aria-label="ZAYLO home"><img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" width="126" height="55"></a>
        <div class="payment-header-tools">
            <span class="payment-header-label">Checkout</span>
            <button class="theme-toggle" type="button" data-theme-toggle data-theme-toggle-label aria-label="Switch theme">Theme</button>
        </div>
    </header>
    <main class="payment-main">@yield('content')</main>
    <footer class="payment-footer">
        <span>&copy; {{ date('Y') }} ZAYLO</span>
        <nav aria-label="Legal"><a href="{{ route('policies.show', 'terms') }}">Terms</a><a href="{{ route('policies.show', 'privacy') }}">Privacy</a><a href="{{ route('policies.show', 'returns') }}">Returns &amp; refunds</a></nav>
    </footer>
    @stack('scripts')
</body>
</html>
