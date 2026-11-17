@extends('layouts.workspace', ['workspace' => 'admin'])
@section('title', 'Account reports')
@section('content')
<section class="seller-page-heading"><div><h1>Account reports</h1><p>Review concerns from buyers, sellers, riders, couriers and logistics partners.</p></div><a class="seller-button seller-button--secondary" href="{{ route('account-reports.index') }}">File a report</a></section>
<section class="seller-panel">
    <form method="GET" class="seller-filter-bar"><label>Status <select name="status"><option value="">All statuses</option>@foreach(\App\Models\AccountReport::STATUSES as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></label><button class="seller-button" type="submit">Apply</button></form>
    <div class="seller-table-wrap"><table class="seller-table"><thead><tr><th>Report</th><th>Reported account</th><th>Submitted by</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($reports as $report)<tr><td>#{{ $report->id }} · {{ \App\Models\AccountReport::CATEGORIES[$report->category] }}</td><td>{{ $report->reported_name }}<span class="seller-cell-note">{{ $report->reported_role }}</span></td><td>{{ $report->reporter?->name ?? 'Deleted account' }}</td><td>{{ \App\Models\AccountReport::STATUSES[$report->status] }}</td><td><a class="seller-button seller-button--small" href="{{ route('admin.account-reports.show', $report) }}">Review</a></td></tr>@empty<tr><td colspan="5">No account reports.</td></tr>@endforelse
    </tbody></table></div>{{ $reports->links() }}
</section>
@endsection
