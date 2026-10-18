@extends('layouts.app')

@section('title', 'Logistics application · ZAYLO')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/seller-application.css') }}?v={{ filemtime(public_path('css/seller-application.css')) }}">
@endpush

@section('content')
@php
    $state = match (true) {
        $user->status === 'rejected' => 'rejected',
        (bool) $provider?->hasApplication() => 'review',
        default => 'incomplete',
    };
    $regions = \App\Models\LogisticsProvider::serviceRegions();
@endphp
<main class="seller-application-page">
    <header class="seller-application-page__header">
        <p class="seller-application-page__eyebrow">ZAYLO Logistics</p>
        <h1>Logistics partner application</h1>
    </header>

    @if($state === 'review')
        <section class="application-status application-status--review" aria-labelledby="application-status-title">
            <i class="fas fa-hourglass-half" aria-hidden="true"></i>
            <div>
                <h2 id="application-status-title">Your application is under review</h2>
                <p>An administrator is checking your business details and documents. You can add riders and accept deliveries once it is approved. We will email {{ $user->email }} with the decision.</p>
            </div>
        </section>
    @elseif($state === 'rejected')
        <section class="application-status application-status--rejected" aria-labelledby="application-status-title">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <div>
                <h2 id="application-status-title">Your application was not approved</h2>
                <p class="application-status__reason"><strong>Reason:</strong> {{ $provider?->rejection_reason ?: 'No reason was given.' }}</p>
                <p>Correct the details below or upload new documents, then resubmit. Your logistics dashboard opens once an administrator approves it.</p>
            </div>
        </section>
    @else
        <section class="application-status application-status--review" aria-labelledby="application-status-title">
            <i class="fas fa-truck-fast" aria-hidden="true"></i>
            <div>
                <h2 id="application-status-title">Complete your logistics application</h2>
                <p>Tell us about your company and upload your business documents. An administrator reviews every application before riders can apply to you.</p>
            </div>
        </section>
    @endif

    @if($state === 'review')
        <section class="application-summary" aria-labelledby="application-summary-title">
            <h2 id="application-summary-title">What you submitted</h2>
            <dl>
                <div><dt>Company name</dt><dd>{{ $provider->name }}</dd></div>
                <div><dt>SEC registration no.</dt><dd>{{ $provider->sec_registration_no }}</dd></div>
                <div><dt>TIN</dt><dd>{{ $provider->tin }}</dd></div>
                <div><dt>Contact person</dt><dd>{{ $user->name }} · {{ $provider->contact_position }}</dd></div>
                <div><dt>Office address</dt><dd>{{ $provider->formatted_address }}</dd></div>
                <div><dt>Service areas</dt><dd>{{ collect($provider->service_areas)->map(fn ($key) => $regions[$key] ?? $key)->implode('; ') }}</dd></div>
                <div><dt>Documents</dt><dd>{{ collect($provider->documents())->keys()->map(fn ($field) => \App\Models\LogisticsProvider::DOCUMENTS[$field])->implode(', ') ?: 'None' }}</dd></div>
                <div><dt>Submitted</dt><dd>{{ $provider->submitted_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
            </dl>
        </section>
    @endif

    @if($editable)
        <form method="POST" action="{{ route('logistics.application.update') }}" enctype="multipart/form-data" class="application-form" data-application-form>
            @csrf
            @if($errors->any())
                <div class="alert-error" role="alert">
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @include('logistics.partials.application-fields', ['provider' => $provider])
            <button type="submit" class="application-form__submit">{{ $provider?->hasApplication() ? 'Resubmit application' : 'Submit application' }}</button>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" data-logout-confirm class="seller-application-page__signout">
        @csrf
        <button type="submit"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Sign out</button>
    </form>
</main>
@endsection

@push('scripts')
<script src="{{ asset('js/address-selector.js') }}?v={{ filemtime(public_path('js/address-selector.js')) }}" defer></script>
<script src="{{ asset('js/seller-application.js') }}?v={{ filemtime(public_path('js/seller-application.js')) }}" defer></script>
@endpush
