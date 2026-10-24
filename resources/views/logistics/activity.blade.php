@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Activity log')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/activity-log.css') }}?v={{ filemtime(public_path('css/activity-log.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Logistics Center</p>
            <h1>Activity log</h1>
            <p>What your company account and your riders did: pickups, sorting, rider assignments, deliveries and sign-ins.</p>
        </div>
    </section>

    @include('partials.activity-log.table', [
        'formAction' => route('logistics.activity'),
        'showActor' => true,
        'roleFilterLabel' => 'Done by',
    ])
@endsection
