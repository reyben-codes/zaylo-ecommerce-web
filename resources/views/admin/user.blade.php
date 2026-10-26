@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', $account->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}?v={{ filemtime(public_path('css/disputes.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/activity-log.css') }}?v={{ filemtime(public_path('css/activity-log.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/document-viewer.css') }}?v={{ filemtime(public_path('css/document-viewer.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/document-viewer.js') }}?v={{ filemtime(public_path('js/document-viewer.js')) }}" defer></script>
@endpush

@section('content')
    @php $role = $account->role === 'courier' ? 'rider' : $account->role; @endphp
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow"><a href="{{ route('admin.users') }}" class="workspace-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Users</a></p>
            <h1>{{ $account->name }}</h1>
            <p>{{ $roleLabels[$role] ?? Str::headline($role) }} · joined {{ $account->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</p>
        </div>
        <div class="seller-page-heading__actions">
            <span class="role-pill account-status--{{ $account->status }}">{{ $statusLabels[$account->status] ?? Str::headline($account->status) }}</span>
            @unless($account->hasRole('admin'))
                <form method="POST" action="{{ route('admin.chat.start') }}">
                    @csrf
                    <input type="hidden" name="participant_id" value="{{ $account->id }}">
                    <button type="submit" class="seller-button seller-button--secondary"><i class="fas fa-comments" aria-hidden="true"></i> Message</button>
                </form>
            @endunless
        </div>
    </section>

    <div class="seller-panel-grid">
        <div class="admin-stack">
            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>Profile</h2></div></header>
                <dl class="workspace-detail-list admin-profile">
                    <div><dt>User ID</dt><dd><strong>#{{ $account->id }}</strong></dd></div>
                    <div><dt>Email</dt><dd>{{ $account->email }}{{ $account->email_verified_at ? '' : ' (not verified)' }}</dd></div>
                    <div><dt>Mobile</dt><dd>{{ $account->phone ?: '—' }}</dd></div>
                    <div><dt>Birthday</dt><dd>{{ $account->date_of_birth ? \Illuminate\Support\Carbon::parse($account->date_of_birth)->format('M j, Y').' (age '.\Illuminate\Support\Carbon::parse($account->date_of_birth)->age.')' : '—' }}</dd></div>
                    <div><dt>Address</dt><dd>{{ $account->address ?: '—' }}</dd></div>
                    <div><dt>Sign-in</dt><dd>{{ $account->google_id ? 'Google and password' : 'Email and password' }}</dd></div>
                    @if($account->approved_at)
                        <div><dt>Approved</dt><dd>{{ $account->approved_at->timezone('Asia/Manila')->format('M j, Y') }}</dd></div>
                    @endif
                    @if($role === 'buyer')
                        <div><dt>ID verified</dt><dd>{{ $account->identity_verified_at ? $account->identity_verified_at->timezone('Asia/Manila')->format('M j, Y') : 'Not yet' }}</dd></div>
                    @endif
                    @if($role === 'logistics' && $account->logisticsProvider)
                        <div><dt>Company</dt><dd>{{ $account->logisticsProvider->name }}{{ $account->logisticsProvider->service_area ? ' · '.$account->logisticsProvider->service_area : '' }}</dd></div>
                    @endif
                    @if($role === 'rider' && $account->rider)
                        <div><dt>Rides for</dt><dd>{{ $account->rider->provider?->name ?? '—' }} · {{ $account->rider->vehicle_type }} {{ $account->rider->plate_no }}</dd></div>
                    @endif
                    @if($role === 'seller' && $account->sellerApplication)
                        <div><dt>Application</dt><dd><a href="{{ route('admin.seller-applications.show', $account) }}" class="workspace-link">View application and documents</a></dd></div>
                    @endif
                </dl>
            </section>

            <section class="seller-panel" aria-labelledby="user-docs-heading">
                <header class="seller-panel__header">
                    <div>
                        <h2 id="user-docs-heading"><i class="fas fa-id-card" aria-hidden="true"></i> Documents &amp; Valid IDs</h2>
                        <p>{{ count($userDocuments ?? []) }} {{ Str::plural('document', count($userDocuments ?? [])) }} on file &middot; Click to view in popup modal</p>
                    </div>
                </header>
                <div class="seller-panel__body">
                    @if(!empty($userDocuments) && count($userDocuments) > 0)
                        <ul class="workspace-documents" data-document-gallery>
                            @foreach($userDocuments as $doc)
                                <li>
                                    <div style="display:flex; flex-direction:column; gap:2px;">
                                        <span><i class="fas fa-file-shield" aria-hidden="true"></i> <strong>{{ $doc['title'] }}</strong></span>
                                        <span class="seller-cell-note">{{ $doc['category'] }} &middot; {{ $doc['date'] ?? 'Submitted' }} &middot; <span class="role-pill account-status--{{ $doc['status'] }}">{{ $doc['status_label'] }}</span></span>
                                    </div>
                                    <a href="{{ $doc['url'] }}" target="_blank" rel="noopener"
                                       class="seller-button seller-button--small seller-button--secondary"
                                       data-document-viewer
                                       data-document-title="{{ $doc['title'] }} &middot; {{ $account->name }}"
                                       data-document-type="{{ $doc['type'] }}">
                                        <i class="fas fa-eye" aria-hidden="true"></i> View in modal
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="seller-empty" style="padding: 24px 16px;">
                            <i class="fas fa-file-circle-xmark" aria-hidden="true"></i>
                            <strong>No documents or IDs uploaded yet</strong>
                            <span>When this user submits a valid government ID or registration papers, they will appear here ready for viewing.</span>
                        </div>
                    @endif
                </div>
            </section>

            @if($buyerStats)
                <section class="seller-panel">
                    <header class="seller-panel__header"><div><h2>Orders</h2><p>{{ $buyerStats['orders'] }} {{ Str::plural('order', $buyerStats['orders']) }} · &#8369;{{ number_format($buyerStats['spent'], 2) }} completed spending</p></div></header>
                    <ul class="workspace-list">
                        @forelse($buyerStats['recent'] as $order)
                            <li class="workspace-list__item">
                                <div class="workspace-list__main"><strong>{{ $order->order_number }}</strong><span>{{ $order->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</span></div>
                                <span>{{ Str::headline($order->status) }} · &#8369;{{ number_format($order->total, 2) }}</span>
                            </li>
                        @empty
                            <li class="admin-empty-inline">No orders yet.</li>
                        @endforelse
                    </ul>
                </section>
            @endif

            @if($sellerStats)
                <section class="seller-panel">
                    <header class="seller-panel__header"><div><h2>{{ $sellerStats['shop'] }}</h2><p>{{ $sellerStats['products'] }} {{ Str::plural('product', $sellerStats['products']) }} listed</p></div></header>
                    <dl class="workspace-detail-list admin-profile">
                        <div><dt>Orders</dt><dd>{{ $sellerStats['row']['orders'] ?? 0 }}</dd></div>
                        <div><dt>Sales</dt><dd>&#8369;{{ number_format($sellerStats['row']['sales'] ?? 0, 2) }}</dd></div>
                        <div><dt>Commission earned</dt><dd>&#8369;{{ number_format(($sellerStats['row']['earned'] ?? 0) + ($sellerStats['row']['settled'] ?? 0), 2) }}</dd></div>
                    </dl>
                </section>
            @endif
        </div>

        <div class="admin-stack">
            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>Account status</h2><p>Every change is emailed to {{ Str::before($account->name, ' ') ?: 'the user' }}.</p></div></header>
                <div class="admin-pad">
                    @include('admin.partials.account-actions', ['account' => $account])
                </div>
            </section>

            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>Recent activity</h2></div><a href="{{ route('admin.activity', ['user' => $account->id]) }}" class="seller-link">Full activity log</a></header>
                @include('partials.activity-log.feed', ['logs' => $activity, 'showDevice' => true, 'emptyText' => 'No activity recorded for this account yet.'])
            </section>

            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>Complaints</h2></div></header>
                <ul class="workspace-list">
                    @forelse($disputes as $dispute)
                        <li class="workspace-list__item">
                            <div class="workspace-list__main">
                                <strong><a href="{{ route('admin.disputes.show', $dispute) }}" class="workspace-link">#{{ $dispute->id }} · {{ $dispute->category_label }}</a></strong>
                                <span>Order {{ $dispute->order?->order_number }} · as {{ Str::lower($dispute->roleOf($account->id)) }}</span>
                            </div>
                            <span class="case-pill case-pill--{{ $dispute->status }}">{{ $dispute->status_label }}</span>
                        </li>
                    @empty
                        <li class="admin-empty-inline">No complaints involve this account.</li>
                    @endforelse
                </ul>
            </section>

            @if($role === 'seller')
                <section class="seller-panel">
                    <header class="seller-panel__header"><div><h2>Compliance warnings</h2></div><a href="{{ route('admin.compliance') }}" class="seller-link">Compliance</a></header>
                    <ul class="workspace-list">
                        @forelse($warnings as $warning)
                            <li class="workspace-list__item">
                                <div class="workspace-list__main">
                                    <strong>{{ $warning->reason_label }}</strong>
                                    <span>{{ $warning->product?->name ?? 'Whole shop' }} · {{ $warning->action_label }} · {{ $warning->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</span>
                                </div>
                                <span class="seller-cell-note">{{ $warning->acknowledged_at ? 'Read' : 'Unread' }}</span>
                            </li>
                        @empty
                            <li class="admin-empty-inline">No warnings.</li>
                        @endforelse
                    </ul>
                </section>
            @endif
        </div>
    </div>
@endsection
