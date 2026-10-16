@extends('layouts.app')

@section('title', $policy->title.' · ZAYLO')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/legal.css') }}?v={{ filemtime(public_path('css/legal.css')) }}">
@endpush

@section('content')
<main class="legal-page">
    <header class="legal-page__header">
        <p class="legal-page__eyebrow">ZAYLO marketplace</p>
        <h1>{{ $policy->title }}</h1>
        <p>Last updated {{ $policy->updated_at?->timezone('Asia/Manila')->format('F j, Y') }}.</p>
    </header>

    <section class="legal-page__body">
        {!! \App\Support\PolicyText::toHtml($policy->body) !!}
    </section>
</main>
@endsection
