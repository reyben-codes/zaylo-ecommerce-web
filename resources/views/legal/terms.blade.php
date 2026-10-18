@extends('layouts.app')

@section('title', 'Terms and Privacy Policy · ZAYLO')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/legal.css') }}?v={{ filemtime(public_path('css/legal.css')) }}">
@endpush

@section('content')
<main class="legal-page">
    <header class="legal-page__header">
        <p class="legal-page__eyebrow">ZAYLO marketplace</p>
        <h1>Terms and Privacy Policy</h1>
        <p>Last updated October 12, 2026. By creating a ZAYLO account you agree to these terms.</p>
    </header>

    @include('legal.partials.terms-sections')
</main>
@endsection
