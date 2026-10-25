@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Seller application')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/document-viewer.css') }}?v={{ filemtime(public_path('css/document-viewer.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/document-viewer.js') }}?v={{ filemtime(public_path('js/document-viewer.js')) }}" defer></script>
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow"><a href="{{ route('admin.registrations', ['role' => 'seller']) }}" class="workspace-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Approvals</a></p>
            <h1>{{ $application?->shop_name ?? $applicant->sellerStoreName() }}</h1>
            <p>Seller application from {{ $applicant->name }}. Check that the details match the documents before approving.</p>
        </div>
        <div class="seller-page-heading__actions">
            @include('admin.partials.account-actions', ['account' => $applicant])
        </div>
    </section>

    <div class="workspace-application-grid">
        <section class="seller-panel" aria-labelledby="applicant-heading">
            <header class="seller-panel__header"><div><h2 id="applicant-heading">Applicant</h2></div></header>
            <dl class="seller-panel__body workspace-detail-list">
                <div><dt>Name</dt><dd>{{ $applicant->name }}</dd></div>
                <div><dt>Email</dt><dd>{{ $applicant->email }} @if($applicant->email_verified_at)<span class="seller-cell-note">Verified</span>@endif</dd></div>
                <div><dt>Mobile</dt><dd>{{ $applicant->phone ?: '—' }}</dd></div>
                <div><dt>Account status</dt><dd><span class="role-pill account-status--{{ $applicant->status }}">{{ \App\Http\Controllers\AdminController::STATUS_LABELS[$applicant->status] ?? Str::headline($applicant->status) }}</span></dd></div>
                <div><dt>Signed up</dt><dd>{{ $applicant->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
            </dl>
        </section>

        @if($application)
            <section class="seller-panel" aria-labelledby="business-heading">
                <header class="seller-panel__header"><div><h2 id="business-heading">Shop and business</h2></div></header>
                <dl class="seller-panel__body workspace-detail-list">
                    <div><dt>Shop name</dt><dd>{{ $application->shop_name }}</dd></div>
                    <div><dt>Shop category</dt><dd>{{ $application->category_label }}</dd></div>
                    <div><dt>Seller type</dt><dd>{{ $application->seller_type_label }}</dd></div>
                    <div><dt>TIN</dt><dd>{{ $application->tin }}</dd></div>
                    <div><dt>Pickup address</dt><dd>{{ $application->formatted_address }}</dd></div>
                    <div><dt>Submitted</dt><dd>{{ $application->submitted_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
                    @if($application->status === 'rejected')
                        <div><dt>Rejection reason</dt><dd>{{ $application->rejection_reason }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="seller-panel" aria-labelledby="documents-heading">
                <header class="seller-panel__header"><div><h2 id="documents-heading">Documents</h2><p>Required for a {{ Str::lower($application->seller_type_label) }}. Open one to view it here.</p></div></header>
                <div class="seller-panel__body"><ul class="workspace-documents" data-document-gallery>
                    @foreach($application->requiredDocuments() as $field)
                        <li>
                            <span><i class="fas fa-file-lines" aria-hidden="true"></i> {{ $documents[$field] }}</span>
                            @if($application->{$field.'_path'})
                                <a href="{{ route('admin.seller-applications.document', [$applicant, $field]) }}" target="_blank" rel="noopener" class="seller-button seller-button--small seller-button--secondary" data-document-viewer data-document-title="{{ $documents[$field] }}" data-document-type="{{ \App\Support\DocumentPreview::type($application->{$field.'_path'}) }}">Open<span class="sr-only"> {{ $documents[$field] }}</span></a>
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
                    <strong>No application details yet</strong>
                    <span>This seller signed up before applications were required, or with Google. They will be asked to complete the application when they sign in.</span>
                </div>
            </section>
        @endif
    </div>
@endsection
