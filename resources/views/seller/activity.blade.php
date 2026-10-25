@extends('layouts.seller')

@section('title', 'Activity Log')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/activity-log.css') }}?v={{ filemtime(public_path('css/activity-log.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Activity log</h1>
            <p>Your sign-ins, product and stock changes, order updates and store settings, newest first.</p>
        </div>
    </section>

    @include('partials.activity-log.table', ['formAction' => route('seller.activity')])
@endsection
