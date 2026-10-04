@extends('layouts.app')

@section('title', 'ZAYLO · Messages')
@section('body-class', 'messaging-page')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/messaging.css') }}?v={{ filemtime(public_path('css/messaging.css')) }}">
@endpush
@section('content')
<main class="messaging-buyer-main">@include('partials.messaging')</main>
@endsection
@push('scripts')
    <script src="{{ asset('js/messaging.js') }}?v={{ filemtime(public_path('js/messaging.js')) }}" defer></script>
@endpush
