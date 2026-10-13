@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Logistics Partners')

@section('content')
    @php
        $statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended', 'rejected' => 'Rejected'];
        $statusPills = ['pending' => 'pending', 'approved' => 'active', 'suspended' => 'suspended', 'rejected' => 'rejected'];
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>Logistics partners</h1>
            <p>Companies that deliver for ZAYLO. Each approved partner reviews the riders who apply to it.</p>
        </div>
    </section>

    <section class="seller-panel">
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th class="is-numeric">Riders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($partners as $partner)
                        <tr>
                            <td>
                                <strong>{{ $partner->name }}</strong>
                                <span class="seller-cell-note">{{ $partner->service_area ? 'Serves '.$partner->service_area : 'No service area given' }}</span>
                            </td>
                            <td>
                                {{ $partner->owner?->name ?? '—' }}
                                <span class="seller-cell-note">{{ $partner->owner?->email }} · {{ $partner->contact_phone ?: 'no phone' }}</span>
                            </td>
                            <td><span class="role-pill account-status--{{ $statusPills[$partner->status] ?? 'pending' }}">{{ $statusLabels[$partner->status] ?? Str::headline($partner->status) }}</span></td>
                            <td class="is-numeric">
                                <strong>{{ $partner->approved_riders_count }}</strong>
                                <span class="seller-cell-note">{{ $partner->pending_riders_count }} awaiting review</span>
                            </td>
                            <td>
                                @if($partner->owner)
                                    @include('admin.partials.account-actions', ['account' => $partner->owner])
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="seller-empty">
                                    <i class="fas fa-truck-fast" aria-hidden="true"></i>
                                    <strong>No logistics partners yet</strong>
                                    <span>Companies can apply from the registration page under "Become a logistics partner".</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($partners->hasPages())
            <div class="seller-pagination">{{ $partners->links() }}</div>
        @endif
    </section>
@endsection
