@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Dashboard')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>Welcome back, {{ Str::before($user->name, ' ') ?: $user->name }}</h1>
            <p>Review new partner applications and keep marketplace accounts in good standing.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('admin.registrations') }}" class="seller-button"><i class="fas fa-user-check" aria-hidden="true"></i> Review applications</a>
        </div>
    </section>

    <section class="seller-stat-grid" aria-label="Marketplace summary">
        <a href="{{ route('admin.registrations') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Awaiting approval</span>
            <strong class="seller-stat__value">{{ number_format($pendingCount) }}</strong>
            <span class="seller-stat__note{{ $pendingCount ? ' seller-stat__note--warning' : '' }}">{{ $pendingCount ? 'Sellers and logistics partners' : 'All caught up' }}</span>
        </a>
        <a href="{{ route('admin.users') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-users" aria-hidden="true"></i> Accounts</span>
            <strong class="seller-stat__value">{{ number_format($roleCounts->sum()) }}</strong>
            <span class="seller-stat__note">{{ $roleCounts['buyer'] }} buyers · {{ $roleCounts['seller'] }} sellers</span>
        </a>
        <a href="{{ route('admin.logistics') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-truck-fast" aria-hidden="true"></i> Logistics partners</span>
            <strong class="seller-stat__value">{{ number_format($partnerCount) }}</strong>
            <span class="seller-stat__note">{{ $roleCounts['rider'] }} {{ Str::plural('rider', $roleCounts['rider']) }} registered</span>
        </a>
        <article class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-receipt" aria-hidden="true"></i> Orders</span>
            <strong class="seller-stat__value">{{ number_format($orderCount) }}</strong>
            <span class="seller-stat__note">{{ number_format($productCount) }} active products</span>
        </article>
    </section>

    @if($pendingIdCount > 0)
        <aside class="seller-notice">
            <i class="fas fa-id-card" aria-hidden="true"></i>
            <div>
                <strong>{{ $pendingIdCount }} buyer {{ Str::plural('ID', $pendingIdCount) }} waiting for review</strong>
                <p>These buyers can't check out until you approve their ID. <a href="{{ route('admin.identity') }}" class="seller-link">Review IDs</a></p>
            </div>
        </aside>
    @endif

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div>
                <h2>Oldest pending applications</h2>
                <p>Sellers and logistics partners can't sign in until you approve them.</p>
            </div>
            <a href="{{ route('admin.registrations') }}" class="seller-link">View all <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </header>
        @if($pendingApplications->isEmpty())
            <div class="seller-empty">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <strong>No applications waiting</strong>
                <span>New seller and logistics partner sign-ups appear here.</span>
            </div>
        @else
            <ul class="workspace-list">
                @foreach($pendingApplications as $applicant)
                    <li class="workspace-list__item">
                        <div class="workspace-list__main">
                            <strong>{{ $applicant->name }}</strong>
                            <span>{{ $applicant->email }} · applied {{ $applicant->created_at?->diffForHumans() }}</span>
                        </div>
                        <span class="role-pill">{{ $roleLabels[$applicant->role] ?? Str::headline($applicant->role) }}</span>
                        @include('admin.partials.account-actions', ['account' => $applicant])
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
