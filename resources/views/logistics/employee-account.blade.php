@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'My Account')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>My Staff Account</h1>
            <p>Manage your login credentials, personal details, and profile security.</p>
        </div>
        <span class="employee-pill employee-pill--{{ $user->logistics_position }}">
            <i class="fas fa-briefcase" aria-hidden="true"></i> {{ $position['label'] ?? 'Staff' }}
        </span>
    </section>

    @if (session('status'))
        <div class="workspace-alert is-success" role="status">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="workspace-alert is-danger" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Please correct the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($user->must_change_password)
        <div class="employee-security-alert" role="alert">
            <div class="employee-security-alert__message">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                <div>
                    <strong>Mandatory Password Change Required:</strong>
                    <span>Your account is currently using a temporary password. You must change your password below before you can access operational hub tools.</span>
                </div>
            </div>
        </div>
    @endif

    <div class="seller-panel-grid workspace-account-grid">
        <div class="workspace-column">
            @include('partials.account-settings-forms', ['user' => $user, 'routePrefix' => 'logistics.account'])
        </div>

        <div class="workspace-column">
            <section class="seller-panel" aria-labelledby="staff-summary-heading">
                <header class="seller-panel__header">
                    <h2 id="staff-summary-heading">Staff Details</h2>
                </header>
                <dl class="seller-panel__body workspace-detail-list">
                    <div>
                        <dt>Logistics Partner</dt>
                        <dd><strong>{{ $provider->name }}</strong></dd>
                    </div>
                    <div>
                        <dt>Assigned Position</dt>
                        <dd>
                            <span class="employee-pill employee-pill--{{ $user->logistics_position }}">
                                {{ $position['label'] ?? 'Staff' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt>Scope</dt>
                        <dd>{{ $position['description'] ?? 'Hub operations' }}</dd>
                    </div>
                    <div>
                        <dt>Account Status</dt>
                        <dd>
                            <span class="employee-pill employee-pill--active">
                                <i class="fas fa-circle-check" aria-hidden="true"></i> Active
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt>Password Security</dt>
                        <dd>
                            @if ($user->must_change_password)
                                <span class="employee-pill employee-pill--warn">Temporary Password</span>
                            @else
                                <span class="employee-pill employee-pill--active">Secured</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Email Verified</dt>
                        <dd>{{ $user->hasVerifiedEmail() ? 'Verified' : 'Pending verification' }}</dd>
                    </div>
                    <div>
                        <dt>Joined</dt>
                        <dd>{{ $user->created_at?->timezone('Asia/Manila')->format('F j, Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection

