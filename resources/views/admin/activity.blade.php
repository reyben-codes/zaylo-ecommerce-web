@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Activity log')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/activity-log.css') }}?v={{ filemtime(public_path('css/activity-log.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            @if($filteredUser)
                <p class="seller-eyebrow"><a href="{{ route('admin.users.show', $filteredUser) }}" class="workspace-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ $filteredUser->name }}</a></p>
                <h1>Activity of {{ $filteredUser->name }}</h1>
                <p>Everything this account did, newest first. <a href="{{ route('admin.activity') }}" class="workspace-link">Show every account</a></p>
            @else
                <p class="seller-eyebrow">Admin Center</p>
                <h1>Activity log</h1>
                <p>Sign-ins, account changes, orders, parcels and admin decisions across every account, newest first.</p>
            @endif
        </div>
    </section>

    @include('partials.activity-log.table', [
        'formAction' => route('admin.activity'),
        'showActor' => true,
        'actorLink' => true,
        'showDevice' => true,
    ])
@endsection
