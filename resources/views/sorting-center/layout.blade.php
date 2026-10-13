@extends('layouts.app')

@section('title', 'Sorting Center · ZAYLO')

@section('nav-links')
    <nav class="nav-links" aria-label="Sorting Center">
        <a href="{{ route('sorting-center.dashboard') }}">Sorting Center</a>
    </nav>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sorting-center.css') }}?v={{ filemtime(public_path('css/sorting-center.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/sorting-center.js') }}?v={{ filemtime(public_path('js/sorting-center.js')) }}" defer></script>
@endpush

@section('content')
    @php
        $navigation = [
            ['label' => 'Overview', 'route' => 'sorting-center.dashboard', 'icon' => 'fa-chart-pie'],
            ['label' => 'Rider management', 'route' => 'sorting-center.riders', 'icon' => 'fa-users'],
            ['label' => 'Pickup requests', 'route' => 'sorting-center.pickups', 'icon' => 'fa-box-open'],
            ['label' => 'Incoming parcels', 'route' => 'sorting-center.incoming', 'icon' => 'fa-arrow-down'],
            ['label' => 'Parcel sorting', 'route' => 'sorting-center.sorting', 'icon' => 'fa-barcode'],
            ['label' => 'Delivery assignment', 'route' => 'sorting-center.assignments', 'icon' => 'fa-truck-fast'],
            ['label' => 'Delivery monitoring', 'route' => 'sorting-center.monitoring', 'icon' => 'fa-map-location-dot'],
            ['label' => 'Reports', 'route' => 'sorting-center.reports', 'icon' => 'fa-chart-column'],
            ['label' => 'Messages', 'route' => 'sorting-center.chat', 'icon' => 'fa-message'],
            ['label' => 'Account', 'route' => 'sorting-center.account', 'icon' => 'fa-user-gear'],
        ];
    @endphp

    <div class="sc-shell" data-sc-shell>
        <aside class="sc-sidebar" id="sc-sidebar" aria-label="Sorting Center navigation">
            <a class="sc-brand" href="{{ route('sorting-center.dashboard') }}">
                <span class="sc-brand-mark"><i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i></span>
                <span><strong>ZAYLO</strong><small>Sorting Center</small></span>
            </a>

            <div class="sc-nav-label">Workspace</div>
            <nav class="sc-nav">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['route'])]) @if(request()->routeIs($item['route'])) aria-current="page" @endif>
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="sc-sidebar-footer">
                <div class="sc-user-chip">
                    <span class="sc-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="sc-user-copy"><strong>{{ auth()->user()->name }}</strong><small>Sorting Center</small></span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="sc-logout" type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Sign out</button>
                </form>
            </div>
        </aside>

        <div class="sc-main">
            <header class="sc-topbar">
                <button class="sc-mobile-menu" type="button" aria-expanded="false" aria-controls="sc-sidebar" data-sc-menu>
                    <i class="fa-solid fa-bars" aria-hidden="true"></i><span class="sr-only">Toggle navigation</span>
                </button>
                <div class="sc-topbar-title">
                    <span>Operations / {{ trim($__env->yieldContent('page-title', 'Overview')) }}</span>
                    <strong>{{ trim($__env->yieldContent('page-title', 'Overview')) }}</strong>
                </div>
                <div class="sc-topbar-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ now()->format('D, M j, Y') }}</div>
            </header>

            <main class="sc-content">
                @if (session('status'))
                    <div class="sc-alert sc-alert-success" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="sc-alert sc-alert-error" role="alert">{{ $errors->first() }}</div>
                @endif
                @yield('module-content')
            </main>
        </div>
    </div>
@endsection
