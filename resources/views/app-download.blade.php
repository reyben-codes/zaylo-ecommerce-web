@extends('layouts.auth')

@section('title', 'Get the ZAYLO app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/app-download.css') }}?v={{ filemtime(public_path('css/app-download.css')) }}">
@endpush

@section('content')
<main class="app-download">
    <section class="app-download__card" aria-labelledby="app-download-title">
        <img class="app-download__logo" src="{{ asset('images/ZAYLO_LOGO_LIGHT.png') }}" alt="ZAYLO">
        <img class="app-download__icon" src="{{ asset('images/ZAYLO_ICON_DARK.png') }}" alt="" data-theme-fixed>
        <p class="app-download__eyebrow">ZAYLO for Android</p>
        <h1 id="app-download-title">Shop ZAYLO on the app</h1>
        <p class="app-download__lead">On phones and tablets, ZAYLO works through our Android app. Download it to browse, check out and track your orders.</p>

        <a class="app-download__button" href="{{ $downloadUrl }}" rel="noopener">
            <i class="fas fa-download" aria-hidden="true"></i> Download the app (APK)
        </a>
        <p class="app-download__note">Free · Android 7.0 or newer</p>

        <ol class="app-download__steps">
            <li><strong>Download</strong> the app with the button above.</li>
            <li><strong>Open</strong> the downloaded <code>zaylo.apk</code>. If Android asks, allow your browser to install unknown apps.</li>
            <li><strong>Sign in</strong> with your ZAYLO account, or create one in the app.</li>
        </ol>
        <p class="app-download__fine">The app is not on the Play Store yet, so Google Play Protect may warn you the first time. Choose <strong>Install anyway</strong>. To update, download it again and install over the old version; you stay signed in.</p>
    </section>

    <nav class="app-download__portals" aria-label="Business accounts">
        <p>Selling or delivering with ZAYLO? These stay on the website:</p>
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
