@extends('sorting-center.layout')

@section('page-title', 'Rider management')

@section('module-content')
    <div class="sc-page-heading">
        <div><div class="sc-eyebrow">People & fleet</div><h1>Rider management</h1><p>Review courier applications and manage registered rider availability.</p></div>
    </div>

    <section class="sc-card" style="margin-bottom:20px">
        <div class="sc-card-header"><div><h2>Pending applications</h2><p>Approvals update the existing user account status.</p></div><span class="sc-badge sc-badge-warning">{{ $applications->total() }} pending</span></div>
        <div class="sc-table-wrap">
            <table class="sc-table">
                <thead><tr><th>Applicant</th><th>Email</th><th>Phone</th><th>Applied</th><th>Decision</th></tr></thead>
                <tbody>
                    @forelse ($applications as $applicant)
                        <tr>
                            <td><strong>{{ $applicant->name }}</strong></td><td>{{ $applicant->email }}</td><td>{{ $applicant->phone ?: '—' }}</td><td>{{ $applicant->created_at?->format('M j, Y') ?: '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('sorting-center.riders.applications.update', $applicant) }}">
                                    @csrf
                                    <button class="sc-button sc-button-small" name="decision" value="approved" type="submit">Approve</button>
                                    <button class="sc-button sc-button-danger sc-button-small" name="decision" value="disapproved" type="submit">Disapprove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="sc-empty"><i class="fa-solid fa-user-check" aria-hidden="true"></i>No pending rider applications.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sc-pagination">{{ $applications->links() }}</div>
    </section>

    <section class="sc-card">
        <div class="sc-card-header"><div><h2>Registered riders</h2><p>Enable or pause an existing rider account using its current active flag.</p></div></div>
        <div class="sc-table-wrap">
            <table class="sc-table">
                <thead><tr><th>Rider</th><th>Provider</th><th>Vehicle</th><th>Plate number</th><th>Account status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($riders as $rider)
                        <tr>
                            <td><strong>{{ $rider->user?->name ?: 'Rider #'.$rider->id }}</strong><small>{{ $rider->user?->email }}</small></td><td>{{ $rider->provider?->name ?: '—' }}</td><td>{{ $rider->vehicle_type ?: '—' }}</td><td>{{ $rider->plate_no ?: '—' }}</td>
                            <td><span @class(['sc-badge', 'sc-badge-success' => $rider->is_active, 'sc-badge-warning' => ! $rider->is_active])>{{ $rider->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('sorting-center.riders.status', $rider) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $rider->is_active ? 0 : 1 }}">
                                    <button class="sc-button sc-button-secondary sc-button-small" type="submit">{{ $rider->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="sc-empty"><i class="fa-solid fa-people-group" aria-hidden="true"></i>No registered riders to display.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sc-pagination">{{ $riders->links() }}</div>
    </section>
@endsection
