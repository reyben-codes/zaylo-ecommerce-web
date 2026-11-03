@extends($layout, $layoutData)

@section('title', 'Help & support')

@push('styles')
    @if($layout === 'layouts.app')
        <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
        <link rel="stylesheet" href="{{ asset('css/seller-workspace.css') }}?v={{ filemtime(public_path('css/seller-workspace.css')) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/logistics-chat.css') }}?v={{ filemtime(public_path('css/logistics-chat.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}?v={{ filemtime(public_path('css/disputes.css')) }}">
@endpush

@section('content')
    <div class="case-page">
        <section class="seller-page-heading">
            <div>
                <p class="seller-eyebrow">Help</p>
                <h1>Help &amp; support</h1>
                <p>Message the ZAYLO team about your account, an order, or anything else. We usually reply within a day.</p>
            </div>
            @if(auth()->user()->hasRole('buyer', 'seller', 'rider'))
                <div class="seller-page-heading__actions">
                    <a href="{{ route('disputes.index') }}" class="seller-button seller-button--secondary"><i class="fas fa-scale-balanced" aria-hidden="true"></i> My complaints</a>
                </div>
            @endif
        </section>

        @include('partials.logistics-chat', [
            'pageRoute' => 'support',
            'showList' => false,
            'fetchRoute' => 'support.messages',
            'sendRoute' => 'support.send',
            'emptyText' => 'Write to the ZAYLO support team.',
        ])
    </div>
@endsection
