@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Staff Dashboard')

@section('content')
    @php
        $actionCards = [
            [
                'route' => 'logistics.scan',
                'title' => 'Scan Parcel',
                'icon' => 'fa-barcode',
                'description' => 'Scan incoming, sorting or dispatch barcodes for status updates and waybills.',
            ],
            [
                'route' => 'logistics.incoming',
                'title' => 'Incoming Parcels',
                'icon' => 'fa-arrow-down',
                'description' => 'Receive driver handovers and parcels arriving from sellers at the hub.',
            ],
            [
                'route' => 'logistics.sorting',
                'title' => 'Sorting Center',
                'icon' => 'fa-layer-group',
                'description' => 'Sort arriving packages by delivery zones and assign dispatch routes.',
            ],
            [
                'route' => 'logistics.pickups',
                'title' => 'Seller Pickups',
                'icon' => 'fa-box-open',
                'description' => 'Review scheduled pickup requests and dispatch first-mile couriers.',
            ],
            [
                'route' => 'logistics.assignments',
                'title' => 'Assign Riders',
                'icon' => 'fa-people-arrows',
                'description' => 'Allocate sorted deliveries and scheduled pickups to active riders.',
            ],
            [
                'route' => 'logistics.deliveries',
                'title' => 'Deliveries Manifest',
                'icon' => 'fa-route',
                'description' => 'Track in-progress, completed, and failed delivery attempts in real-time.',
            ],
            [
                'route' => 'logistics.returns',
                'title' => 'Parcel Returns',
                'icon' => 'fa-rotate-left',
                'description' => 'Process returned shipments and coordinate return delivery to sellers.',
            ],
            [
                'route' => 'logistics.monitoring',
                'title' => 'Live Monitoring',
                'icon' => 'fa-satellite-dish',
                'description' => 'Real-time overview of active riders and on-the-road deliveries.',
            ],
            [
                'route' => 'logistics.couriers',
                'title' => 'Pickup Fleet (First Mile)',
                'icon' => 'fa-truck-pickup',
                'description' => 'Coordinate dedicated pickup fleet for merchant bulk collections.',
            ],
            [
                'route' => 'logistics.riders',
                'title' => 'Rider Fleet',
                'icon' => 'fa-motorcycle',
                'description' => 'Manage rider applications, active roster, and vehicle verification.',
            ],
            [
                'route' => 'logistics.attendance',
                'title' => 'Rider Attendance',
                'icon' => 'fa-user-clock',
                'description' => 'Display hub check-in QR code and review rider shift attendance logs.',
            ],
            [
                'route' => 'logistics.reports',
                'title' => 'Hub Reports',
                'icon' => 'fa-chart-column',
                'description' => 'Export operational metrics, delivery success rates, and turnaround logs.',
            ],
            [
                'route' => 'logistics.cash',
                'title' => 'Cash Handover & COD',
                'icon' => 'fa-money-bill-wave',
                'description' => 'Record COD cash collections, remit funds, and balance cash handovers.',
            ],
            [
                'route' => 'logistics.rider-withdrawals',
                'title' => 'Rider Withdrawals',
                'icon' => 'fa-money-bill-transfer',
                'description' => 'Review and disburse completed rider earnings and withdrawal requests.',
            ],
            [
                'route' => 'logistics.messages',
                'title' => 'Team Messages',
                'icon' => 'fa-comments',
                'description' => 'Coordinate directly with riders, hub dispatchers, and support.',
            ],
        ];

        $availableActions = array_filter($actionCards, fn ($card) => \App\Services\LogisticsAccess::canRoute($user, $card['route']));
    @endphp

    <section class="employee-welcome-banner">
        <div class="employee-welcome-content">
            <span class="workspace-eyebrow">{{ $provider->name }}</span>
            <h1>Welcome back, {{ Str::before($user->name, ' ') ?: $user->name }}</h1>
            <p>{{ $position['description'] ?? 'Your employee workspace is configured for your role in hub operations.' }}</p>
        </div>
        <div class="employee-welcome-badge">
            <span class="employee-pill employee-pill--{{ $user->logistics_position }}">
                <i class="fas fa-briefcase" aria-hidden="true"></i> {{ $position['label'] ?? 'Staff Member' }}
            </span>
            <span class="employee-pill employee-pill--active">
                <i class="fas fa-circle-check" aria-hidden="true"></i> Active Staff
            </span>
        </div>
    </section>

    @if ($user->must_change_password)
        <div class="employee-security-alert" role="alert">
            <div class="employee-security-alert__message">
                <i class="fas fa-key" aria-hidden="true"></i>
                <div>
                    <strong>Temporary password in use.</strong>
                    <span>For account security, you must update your password before accessing all workspace tools.</span>
                </div>
            </div>
            <a href="{{ route('logistics.account') }}" class="employee-btn employee-btn--primary">
                <i class="fas fa-lock" aria-hidden="true"></i> Change Password Now
            </a>
        </div>
    @endif

    <section aria-label="Permitted workspace tools">
        <h2 class="employee-card__title">
            <i class="fas fa-layer-group" aria-hidden="true"></i> Your Operational Tools
        </h2>

        <div class="employee-dashboard-tiles">
            @forelse($availableActions as $action)
                <a href="{{ route($action['route']) }}" class="employee-action-tile">
                    <div>
                        <div class="employee-action-tile__header">
                            <span class="employee-action-tile__icon">
                                <i class="fas {{ $action['icon'] }}" aria-hidden="true"></i>
                            </span>
                            <h3 class="employee-action-tile__title">{{ $action['title'] }}</h3>
                        </div>
                        <p class="employee-action-tile__description">{{ $action['description'] }}</p>
                    </div>
                    <div class="employee-action-tile__footer">
                        <span>Open tool</span>
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </div>
                </a>
            @empty
                <div class="employee-card">
                    <p>No operational modules currently configured for this role.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="employee-info-section" aria-label="Employee and hub information">
        <article class="employee-card">
            <h3 class="employee-card__title">
                <i class="fas fa-shield-halved" aria-hidden="true"></i> Position Limits & Permissions
            </h3>
            <dl class="workspace-detail-list">
                <div>
                    <dt>Assigned Role</dt>
                    <dd><strong>{{ $position['label'] ?? 'Staff' }}</strong></dd>
                </div>
                <div>
                    <dt>Logistics Hub</dt>
                    <dd>{{ $provider->name }}</dd>
                </div>
                <div>
                    <dt>Permitted Tools</dt>
                    <dd>{{ count($availableActions) }} operational areas</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><span class="employee-pill employee-pill--active">Active</span></dd>
                </div>
            </dl>
        </article>

        <article class="employee-card">
            <h3 class="employee-card__title">
                <i class="fas fa-user-gear" aria-hidden="true"></i> Staff Credentials
            </h3>
            <dl class="workspace-detail-list">
                <div>
                    <dt>Full Name</dt>
                    <dd>{{ $user->name }}</dd>
                </div>
                <div>
                    <dt>Work Email</dt>
                    <dd>{{ $user->email }}</dd>
                </div>
                <div>
                    <dt>Contact Phone</dt>
                    <dd>{{ $user->phone ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt>Security</dt>
                    <dd>
                        @if ($user->must_change_password)
                            <span class="employee-pill employee-pill--warn">Temporary Password</span>
                        @else
                            <span class="employee-pill employee-pill--active">Secured</span>
                        @endif
                    </dd>
                </div>
            </dl>
            <div class="employee-form-actions">
                <a href="{{ route('logistics.account') }}" class="employee-btn employee-btn--outline">
                    <i class="fas fa-key" aria-hidden="true"></i> Manage Password & Profile
                </a>
            </div>
        </article>
    </section>
@endsection

