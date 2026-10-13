{{-- Admin and logistics portals. Same design system as Seller Center (layouts/seller). --}}
@php
    $workspace ??= 'admin';
    $viewer = auth()->user();
    $portals = [
        'admin' => [
            'title' => 'Admin Center',
            'home' => 'admin.dashboard',
            'account' => 'admin.account',
            'context' => 'Signed in as <strong>'.e($viewer->name).'</strong>',
            'icon' => 'fa-shield-halved',
            'nav' => [
                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'fa-home'],
                ['route' => 'admin.registrations', 'label' => 'Approvals', 'icon' => 'fa-user-check'],
                ['route' => 'admin.identity', 'label' => 'ID checks', 'icon' => 'fa-id-card'],
                ['route' => 'admin.users', 'label' => 'Users', 'icon' => 'fa-users'],
                ['route' => 'admin.logistics', 'label' => 'Logistics', 'icon' => 'fa-truck-fast'],
                ['route' => 'admin.account', 'label' => 'Account', 'icon' => 'fa-user'],
            ],
        ],
        'logistics' => [
            'title' => 'Logistics Center',
            'home' => 'logistics.dashboard',
            'account' => 'logistics.account',
            'context' => 'Managing <strong>'.e($viewer->logisticsProvider?->name ?? 'your company').'</strong>',
            'icon' => 'fa-truck-fast',
            'nav' => [
                ['route' => 'logistics.dashboard', 'label' => 'Dashboard', 'icon' => 'fa-home'],
                ['route' => 'logistics.riders', 'label' => 'Riders', 'icon' => 'fa-motorcycle'],
                ['route' => 'logistics.account', 'label' => 'Account', 'icon' => 'fa-user'],
            ],
        ],
    ];
    $portal = $portals[$workspace];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | ZAYLO {{ $portal['title'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @foreach(['seller-sidebar', 'seller-header', 'seller-workspace', 'workspace-pages'] as $stylesheet)
        <link rel="stylesheet" href="{{ asset("css/{$stylesheet}.css") }}?v={{ filemtime(public_path("css/{$stylesheet}.css")) }}">
    @endforeach
    @stack('styles')
</head>
<body class="seller-workspace workspace-{{ $workspace }}">
    <header class="seller-site-header">
        <div class="seller-site-header__shop">
            <i class="fa-solid {{ $portal['icon'] }}" aria-hidden="true"></i>
            <span>{!! $portal['context'] !!}</span>
        </div>
        <a href="{{ route($portal['home']) }}" class="seller-site-header__logo" aria-label="{{ $portal['title'] }} dashboard">
            <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO">
        </a>
        <div class="seller-site-header__actions">
            <a href="{{ route($portal['account']) }}" class="seller-site-header__button" aria-label="Account">
                <i class="fa-regular fa-user" aria-hidden="true"></i>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="seller-site-header__logout">
                @csrf
                <button type="submit" class="seller-site-header__button" aria-label="Log out">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </header>

    @include('partials.notifications')

    <div class="seller-shell">
        <aside class="sidebar seller-sidebar" id="sidebar" aria-label="{{ $portal['title'] }} navigation">
            <p class="seller-sidebar__title">{{ $portal['title'] }}</p>
            <nav class="seller-sidebar__nav">
                @foreach($portal['nav'] as $item)
                    @php $active = request()->routeIs($item['route'], $item['route'].'.*'); @endphp
                    <a href="{{ route($item['route']) }}" class="seller-sidebar__link{{ $active ? ' is-active' : '' }}" @if($active) aria-current="page" @endif>
                        <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
                <div class="seller-sidebar__divider" aria-hidden="true"></div>
                <form method="POST" action="{{ route('logout') }}" class="seller-sidebar__logout-form">
                    @csrf
                    <button type="submit" class="seller-sidebar__link seller-sidebar__logout">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </nav>
        </aside>

        <main class="seller-main" id="main-content">
            @yield('content')
        </main>
    </div>

    <script src="{{ asset('js/notifications.js') }}?v={{ filemtime(public_path('js/notifications.js')) }}" defer></script>
    <script src="{{ asset('js/smooth-disclosure.js') }}?v={{ filemtime(public_path('js/smooth-disclosure.js')) }}" defer></script>
    <script src="{{ asset('js/seller-workspace.js') }}?v={{ filemtime(public_path('js/seller-workspace.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
