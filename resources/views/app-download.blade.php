@extends('layouts.auth')

@php
    // Shoppers on Android, or riders, who sign up on the website but sign in only in the app.
    $rider = ($audience ?? null) === 'rider';
@endphp

@section('title', $rider ? 'ZAYLO Riders: get the app' : 'Get the ZAYLO app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/app-download.css') }}?v={{ filemtime(public_path('css/app-download.css')) }}">
@endpush

@section('content')
<main class="app-download">
    <section class="app-download__card" aria-labelledby="app-download-title">
        <img class="app-download__logo" src="{{ asset('images/ZAYLO_LOGO_LIGHT.png') }}" alt="ZAYLO">
        <img class="app-download__icon" src="{{ asset('images/ZAYLO_ICON_DARK.png') }}" alt="" data-theme-fixed>
        @if(session('status'))
            <p class="app-download__status" role="status">{{ session('status') }}</p>
        @endif
        @if($rider)
            <p class="app-download__eyebrow">ZAYLO Riders</p>
            <h1 id="app-download-title">Deliver with the ZAYLO app</h1>
            <p class="app-download__lead">Riders sign in on our Android app, not the website. Use it to accept deliveries, scan parcels, take delivery photos and collect returns.</p>
        @else
            <p class="app-download__eyebrow">ZAYLO for Android</p>
            <h1 id="app-download-title">Shop ZAYLO on the app</h1>
            <p class="app-download__lead">On phones and tablets, ZAYLO works through our Android app. Download it to browse, check out and track your orders.</p>
        @endif

        <a class="app-download__button" href="{{ $downloadUrl }}" rel="noopener">
            <i class="fas fa-download" aria-hidden="true"></i> Download the app (APK)
        </a>
        <p class="app-download__note">Free · Android 7.0 or newer</p>

        <ol class="app-download__steps">
            <li><strong>Download</strong> the app with the button above.</li>
            <li><strong>Open</strong> the downloaded <code>zaylo.apk</code>. If Android asks, allow your browser to install unknown apps.</li>
            @if($rider)
                <li><strong>Sign in:</strong> open the menu, choose <strong>Login as a Rider</strong>, and use the email and password you registered with.</li>
            @else
                <li><strong>Sign in</strong> with your ZAYLO account, or create one in the app.</li>
            @endif
        </ol>
        <p class="app-download__fine">The app is not on the Play Store yet, so Google Play Protect may warn you the first time. Choose <strong>Install anyway</strong>. To update, download it again and install over the old version; you stay signed in.</p>
    </section>

    <nav class="app-download__portals" aria-label="Business accounts">
        @if($rider)
            <p>New rider? <a href="{{ route('register', ['role' => 'courier']) }}">Apply to deliver with ZAYLO</a>. Other accounts sign in on the website:</p>
        @else
            <p>Selling or delivering with ZAYLO? These stay on the website:</p>
        @endif
        <a href="{{ route('seller.login') }}">Seller Center</a>
        <a href="{{ route('logistics.login') }}">Logistics partners</a>
        <a href="{{ route('admin.login') }}">Admin</a>
    </nav>

    <footer class="app-download__legal">
        <a href="{{ route('policies.show', 'terms') }}">Terms of Service</a>
        <a href="{{ route('policies.show', 'privacy') }}">Privacy Policy</a>
    </footer>
</main>
@endsection
