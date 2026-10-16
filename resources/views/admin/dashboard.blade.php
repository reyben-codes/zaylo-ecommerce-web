@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}?v={{ filemtime(public_path('css/disputes.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>Welcome back, {{ Str::before($user->name, ' ') ?: $user->name }}</h1>
            <p>The marketplace at a glance: what needs you today, sales in the last 30 days, and account activity.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('admin.reports') }}" class="seller-button seller-button--secondary"><i class="fas fa-chart-column" aria-hidden="true"></i> Reports</a>
        </div>
    </section>

    <section class="seller-panel admin-attention" aria-labelledby="attention-title">
        <header class="seller-panel__header">
            <div><h2 id="attention-title">Needs your attention</h2><p>Updated each time you open the dashboard.</p></div>
        </header>
        @if(empty($attention))
            <div class="seller-empty">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <strong>All caught up</strong>
                <span>No applications, complaints, IDs or messages are waiting.</span>
            </div>
        @else
            <ul class="admin-attention__list">
                @foreach($attention as $item)
                    <li>
                        <a href="{{ $item['route'] }}">
                            <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                            <strong>{{ number_format($item['count']) }}</strong>
                            <span>{{ $item['label'] }}</span>
                            <i class="fas fa-arrow-right admin-attention__go" aria-hidden="true"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="seller-stat-grid" aria-label="Last 30 days">
        <a href="{{ route('admin.reports') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Sales, 30 days</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($sales['gross'], 2) }}</strong>
            <span class="seller-stat__note">{{ number_format($sales['orders']) }} {{ Str::plural('order', $sales['orders']) }} · {{ $sales['cancelled'] }} cancelled</span>
        </a>
        <a href="{{ route('admin.commissions') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-percent" aria-hidden="true"></i> Commission earned</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($sales['commissionEarned'], 2) }}</strong>
            <span class="seller-stat__note">&#8369;{{ number_format($sales['commissionPending'], 2) }} pending on open orders</span>
        </a>
        <a href="{{ route('admin.users') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-users" aria-hidden="true"></i> Accounts</span>
            <strong class="seller-stat__value">{{ number_format($roleCounts->sum()) }}</strong>
            <span class="seller-stat__note">{{ $roleCounts['buyer'] }} buyers · {{ $roleCounts['seller'] }} sellers · {{ $roleCounts['rider'] }} riders</span>
        </a>
        <a href="{{ route('admin.logistics') }}" class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-store" aria-hidden="true"></i> Catalog and partners</span>
            <strong class="seller-stat__value">{{ number_format($productCount) }}</strong>
            <span class="seller-stat__note">active products · {{ $partnerCount }} logistics {{ Str::plural('partner', $partnerCount) }}</span>
        </a>
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

    <div class="seller-panel-grid">
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
                                <strong><a href="{{ route('admin.users.show', $applicant) }}" class="workspace-link">{{ $applicant->name }}</a></strong>
                                <span>{{ $applicant->email }} · applied {{ $applicant->created_at?->diffForHumans() }}</span>
                            </div>
                            <span class="role-pill">{{ $roleLabels[$applicant->role] ?? Str::headline($applicant->role) }}</span>
                            @include('admin.partials.account-actions', ['account' => $applicant])
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="seller-panel">
            <header class="seller-panel__header">
                <div><h2>Open complaints</h2><p>Newest first.</p></div>
                <a href="{{ route('admin.disputes') }}" class="seller-link">All cases <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </header>
            @if($openDisputes->isEmpty())
                <div class="seller-empty">
                    <i class="fas fa-scale-balanced" aria-hidden="true"></i>
                    <strong>No open complaints</strong>
                    <span>Buyers file complaints from their orders.</span>
                </div>
            @else
                <ul class="workspace-list">
                    @foreach($openDisputes as $dispute)
                        <li class="workspace-list__item">
                            <div class="workspace-list__main">
                                <strong><a href="{{ route('admin.disputes.show', $dispute) }}" class="workspace-link">{{ $dispute->category_label }}</a></strong>
                                <span>Order {{ $dispute->order?->order_number ?? '#'.$dispute->order_id }} · {{ $dispute->created_at?->diffForHumans() }}</span>
                            </div>
                            <span class="case-pill case-pill--{{ $dispute->status }}">{{ $dispute->status_label }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
