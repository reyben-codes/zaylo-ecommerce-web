@extends('layouts.workspace', ['workspace' => 'admin'])
@section('title', 'Review account report')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}?v={{ filemtime(public_path('css/account-reports.css')) }}">
@endpush
@section('content')
<div class="account-reports-page">
    <a href="{{ route('admin.account-reports') }}">Back to account reports</a><h1>Report #{{ $report->id }}</h1>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <article class="account-report-card">
        <h2>{{ $report->reported_name }}</h2>
        @if($report->reportedUser)<a href="{{ route('admin.users.show', $report->reportedUser) }}">View reported account</a>@endif
        <p>From {{ $report->reporter?->name ?? 'Deleted account' }} · {{ \App\Models\AccountReport::CATEGORIES[$report->category] }} · {{ \App\Models\AccountReport::STATUSES[$report->status] }}</p>
        @if($report->order_reference)<p>Order reference: {{ $report->order_reference }}</p>@endif
        <p class="account-report-text">{{ $report->details }}</p>
        @if($report->reviewer)<p>Last reviewed by {{ $report->reviewer->name }} · {{ $report->reviewed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
    </article>
    @if($errors->any())<ul class="account-report-errors" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <form method="POST" action="{{ route('admin.account-reports.review', $report) }}" class="account-report-form account-report-card">
        @csrf @method('PATCH')
        <label>Status<select name="status" required>@foreach(\App\Models\AccountReport::STATUSES as $key => $label)<option value="{{ $key }}" @selected(old('status', $report->status) === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Response to the reporter<textarea name="resolution" maxlength="2000" rows="5" placeholder="Explain the outcome. Required when resolving or dismissing a report.">{{ old('resolution', $report->resolution) }}</textarea></label>
        <button type="submit" class="seller-button">Save review</button>
    </form>
</div>
@endsection
