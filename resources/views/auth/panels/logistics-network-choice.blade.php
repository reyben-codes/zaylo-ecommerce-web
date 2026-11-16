{{-- Logistics partner network selection panel.
     Applicants choose between Zaylo Express Delivery, J&T Express, or Flash Express to apply as a regional branch hub. --}}
@php
    $networks = $networks ?? \App\Support\LogisticsNetworks::all();
@endphp

<div class="auth-panel" data-auth-panel="logistics-network-choice">
    <header class="auth-panel__header">
        <span class="auth-panel__label">LOGISTICS PARTNER NETWORK</span>
        <h2 class="auth-panel__title" id="auth-modal-title" tabindex="-1">Choose Logistics Partner</h2>
        <p class="auth-panel__subtitle">Select which logistics partner you want to apply for. Your account will operate as an authorized regional branch hub.</p>
    </header>

    <div class="auth-panel__alerts" data-auth-alerts>
        @if(session('status'))
            <div class="auth-alert auth-alert--success" role="status">{{ session('status') }}</div>
        @endif
    </div>

    <nav class="role-choices" aria-label="Logistics partner networks">
        @foreach($networks as $key => $net)
            <a href="{{ route('register', ['role' => 'logistics', 'network' => $key]) }}" class="role-choice logistics-network-choice" data-network-choice="{{ $key }}">
                <span class="role-choice__icon logistics-network-icon" aria-hidden="true">
                    @if(file_exists(public_path($net['logo'])))
                        <img src="{{ asset($net['logo']) }}" alt="{{ $net['name'] }}" class="logistics-network-img">
                    @else
                        <i class="fas {{ $net['icon'] }}"></i>
                    @endif
                </span>
                <span class="role-choice__body">
                    <span class="role-choice__title-row">
                        <span class="role-choice__title">{{ $net['name'] }}</span>
                        <span class="network-badge network-badge--{{ $net['badge_class'] ?? 'official' }}">{{ $net['badge'] }}</span>
                    </span>
                    <span class="role-choice__text">{{ $net['description'] }}</span>
                    <span class="role-choice__meta">
                        <span><i class="fas fa-warehouse" aria-hidden="true"></i> Regional Branch Hub</span>
                        <span><i class="fas fa-route" aria-hidden="true"></i> Hub-to-Hub Delivery Ready</span>
                        <span><i class="fas fa-user-check" aria-hidden="true"></i> Admin Approval</span>
                    </span>
                </span>
                <i class="fas fa-chevron-right role-choice__arrow" aria-hidden="true"></i>
            </a>
        @endforeach
    </nav>

    <footer class="auth-panel__footer logistics-choice-footer">
        <p><a href="{{ route('register') }}" class="auth-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to account types</a></p>
        <p>Already have an approved account? <a href="{{ route('logistics.login') }}">Logistics Center sign in</a></p>
    </footer>
</div>
