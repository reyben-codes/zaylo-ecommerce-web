@extends('layouts.app')

@section('title', 'Seller application · ZAYLO')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/seller-application.css') }}?v={{ filemtime(public_path('css/seller-application.css')) }}">
@endpush

@section('content')
@php
    $state = match (true) {
        $user->status === 'rejected' => 'rejected',
        (bool) $application => 'review',
        default => 'incomplete',
    };
@endphp
<main class="seller-application-page">
    <header class="seller-application-page__header">
        <p class="seller-application-page__eyebrow">ZAYLO Seller Center</p>
        <h1>Seller application</h1>
    </header>

    @if($state === 'review')
        <section class="application-status application-status--review" aria-labelledby="application-status-title">
            <i class="fas fa-hourglass-half" aria-hidden="true"></i>
            <div>
                <h2 id="application-status-title">Your application is under review</h2>
                <p>An administrator is checking your details and documents. You can add products and receive orders once it is approved. We will email {{ $user->email }} with the decision.</p>
            </div>
        </section>
    @elseif($state === 'rejected')
        <section class="application-status application-status--rejected" aria-labelledby="application-status-title">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <div>
                <h2 id="application-status-title">Your application was not approved</h2>
                <p class="application-status__reason"><strong>Reason:</strong> {{ $application?->rejection_reason ?: 'No reason was given.' }}</p>
                <p>Correct the details below or upload new documents, then resubmit. Your Seller Center opens once an administrator approves it.</p>
            </div>
        </section>
    @else
        <section class="application-status application-status--review" aria-labelledby="application-status-title">
            <i class="fas fa-store" aria-hidden="true"></i>
            <div>
                <h2 id="application-status-title">Complete your seller application</h2>
                <p>Tell us about your shop and upload your documents. An administrator reviews every application before the shop opens.</p>
            </div>
        </section>
    @endif

    @if($application && $state === 'review')
        <section class="application-summary" aria-labelledby="application-summary-title">
            <h2 id="application-summary-title">What you submitted</h2>
            <dl>
                <div><dt>Shop name</dt><dd>{{ $application->shop_name }}</dd></div>
                <div><dt>Shop category</dt><dd>{{ $application->category_label }}</dd></div>
                <div><dt>Seller type</dt><dd>{{ $application->seller_type_label }}</dd></div>
                <div><dt>TIN</dt><dd>{{ $application->tin }}</dd></div>
                <div><dt>Pickup address</dt><dd>{{ $application->formatted_address }}</dd></div>
                <div><dt>Documents</dt><dd>{{ collect($application->documents())->keys()->map(fn ($field) => \App\Models\SellerApplication::DOCUMENTS[$field] ?? $field)->implode(', ') ?: 'None' }}</dd></div>
                <div><dt>Submitted</dt><dd>{{ $application->submitted_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
            </dl>
        </section>
    @endif

    @if($editable)
        <form method="POST" action="{{ route('seller.application.update') }}" enctype="multipart/form-data" class="application-form" data-application-form>
            @csrf
            @if($errors->any())
                <div class="alert-error" role="alert">
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @include('seller.partials.application-fields', ['application' => $application])
            <button type="submit" class="application-form__submit">{{ $application ? 'Resubmit application' : 'Submit application' }}</button>
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
