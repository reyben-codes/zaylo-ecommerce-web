@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Approvals')

@section('content')
    @php
        $tabs = ['all' => 'All', 'seller' => 'Sellers', 'logistics' => 'Logistics partners'];
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>Approvals</h1>
            <p>Approve only applicants with complete, matching details. Riders are reviewed by the logistics partner they applied to.</p>
        </div>
    </section>

    <section class="seller-panel">
        <nav class="workspace-tabs" aria-label="Filter applications">
            @foreach($tabs as $key => $label)
                @php $active = ($role ?? 'all') === $key; @endphp
                <a href="{{ route('admin.registrations', $key === 'all' ? [] : ['role' => $key]) }}" class="workspace-tab{{ $active ? ' is-active' : '' }}" @if($active) aria-current="true" @endif>
                    {{ $label }} <span>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="seller-table-wrap">
            <table class="seller-table workspace-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Applying as</th>
                        <th>Business</th>
                        <th>Email</th>
                        <th>Applied</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $applicant)
                        @php
                            $business = $applicant->role === 'logistics'
                                ? $applicant->logisticsProvider?->name
                                : ($applicant->role === 'seller' ? $applicant->sellerStoreName() : null);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $applicant->name }}</strong>
                                <span class="seller-cell-note">{{ $applicant->phone ?: 'No phone on file' }}</span>
                            </td>
                            <td><span class="role-pill">{{ $roleLabels[$applicant->role] ?? Str::headline($applicant->role) }}</span></td>
                            <td>
                                <strong>{{ $business ?: '—' }}</strong>
                                @if($applicant->role === 'logistics' && $applicant->logisticsProvider?->service_area)
                                    <span class="seller-cell-note">Serves {{ $applicant->logisticsProvider->service_area }}</span>
                                @endif
                                @if($applicant->role === 'seller' && $applicant->relationLoaded('sellerApplication'))
                                    @if($applicant->sellerApplication)
                                        <span class="seller-cell-note">{{ $applicant->sellerApplication->category_label }} · {{ $applicant->sellerApplication->seller_type_label }}</span>
                                    @else
                                        <span class="seller-cell-note">Application details not submitted yet</span>
                                    @endif
                                    <a href="{{ route('admin.seller-applications.show', $applicant) }}" class="workspace-link">Review application <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                                @endif
                            </td>
                            <td>
                                {{ $applicant->email }}
                                <span class="seller-cell-note">{{ $applicant->email_verified_at ? 'Email verified' : 'Email not verified yet' }}</span>
                            </td>
                            <td>{{ $applicant->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</td>
                            <td>@include('admin.partials.account-actions', ['account' => $applicant])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="seller-empty">
                                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                                    <strong>No applications waiting</strong>
                                    <span>New seller and logistics partner sign-ups appear here.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($applications->hasPages())
            <div class="seller-pagination">{{ $applications->links() }}</div>
        @endif
    </section>
@endsection
