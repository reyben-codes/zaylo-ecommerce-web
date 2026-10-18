@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Logistics application')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow"><a href="{{ route('admin.logistics') }}" class="workspace-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Logistics partners</a></p>
            <h1>{{ $provider->name }}</h1>
            <p>Logistics partner application{{ $applicant ? ' from '.$applicant->name : '' }}. Check that the details match the documents before approving.</p>
        </div>
        @if($applicant)
            <div class="seller-page-heading__actions">
                @include('admin.partials.account-actions', ['account' => $applicant])
            </div>
        @endif
    </section>

    <div class="workspace-application-grid">
        <section class="seller-panel" aria-labelledby="applicant-heading">
            <header class="seller-panel__header"><div><h2 id="applicant-heading">Contact person</h2></div></header>
            <dl class="seller-panel__body workspace-detail-list">
                <div><dt>Name</dt><dd>{{ $applicant?->name ?? '—' }}</dd></div>
                <div><dt>Position</dt><dd>{{ $provider->contact_position ?: '—' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $applicant?->email }} @if($applicant?->email_verified_at)<span class="seller-cell-note">Verified</span>@endif</dd></div>
                <div><dt>Mobile</dt><dd>{{ $applicant?->phone ?: $provider->contact_phone ?: '—' }}</dd></div>
                @if($applicant)
                    <div><dt>Account status</dt><dd><span class="role-pill account-status--{{ $applicant->status }}">{{ \App\Http\Controllers\AdminController::STATUS_LABELS[$applicant->status] ?? Str::headline($applicant->status) }}</span></dd></div>
                @endif
                <div><dt>Signed up</dt><dd>{{ $provider->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
            </dl>
        </section>

        @if($provider->hasApplication())
            <section class="seller-panel" aria-labelledby="business-heading">
                <header class="seller-panel__header"><div><h2 id="business-heading">Company and business</h2></div></header>
                <dl class="seller-panel__body workspace-detail-list">
                    <div><dt>Company name</dt><dd>{{ $provider->name }}</dd></div>
                    <div><dt>SEC registration no.</dt><dd>{{ $provider->sec_registration_no }}</dd></div>
                    <div><dt>TIN</dt><dd>{{ $provider->tin }}</dd></div>
                    <div><dt>Office address</dt><dd>{{ $provider->formatted_address }}</dd></div>
                    <div><dt>Service areas</dt><dd>{{ collect($provider->service_areas)->map(fn ($key) => $regions[$key] ?? $key)->implode('; ') }}</dd></div>
                    <div><dt>Submitted</dt><dd>{{ $provider->submitted_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
                    @if($provider->status === 'rejected')
                        <div><dt>Rejection reason</dt><dd>{{ $provider->rejection_reason ?: 'No reason given' }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="seller-panel" aria-labelledby="documents-heading">
                <header class="seller-panel__header"><div><h2 id="documents-heading">Documents</h2><p>Each opens in a new tab.</p></div></header>
                <div class="seller-panel__body"><ul class="workspace-documents">
                    @foreach($documents as $field => $label)
                        <li>
                            <span><i class="fas fa-file-lines" aria-hidden="true"></i> {{ $label }}</span>
                            @if($provider->{$field.'_path'})
                                <a href="{{ route('admin.logistics.document', [$provider, $field]) }}" target="_blank" rel="noopener" class="seller-button seller-button--small seller-button--secondary">Open</a>
                            @else
                                <span class="seller-cell-note">Missing</span>
                            @endif
                        </li>
                    @endforeach
                </ul></div>
            </section>
        @else
            <section class="seller-panel">
                <div class="seller-panel__body seller-empty">
                    <i class="fas fa-file-circle-question" aria-hidden="true"></i>
                    <strong>No business details yet</strong>
                    <span>This partner signed up before business details and documents were required.</span>
                </div>
            </section>
        @endif
    </div>
@endsection
