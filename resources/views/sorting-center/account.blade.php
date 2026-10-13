@extends('sorting-center.layout')

@section('page-title', 'Account management')

@section('module-content')
    <div class="sc-page-heading"><div><div class="sc-eyebrow">Workspace profile</div><h1>Account management</h1><p>View the currently signed-in Sorting Center account.</p></div></div>
    <div class="sc-demo-notice"><i class="fa-solid fa-lock" aria-hidden="true"></i><span>Your profile is shown from the authenticated account. This project has no Sorting Center profile-edit endpoint; details below are read-only, and no changes are being simulated or saved.</span></div>
    <section class="sc-card">
        <div class="sc-card-header"><div><h2>Profile details</h2><p>Account and contact information on file.</p></div><span class="sc-badge sc-badge-success">Signed in</span></div>
        <div class="sc-card-body">
            <div class="sc-profile-grid">
                <div>
                    <div class="sc-profile-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    <div style="margin-top:13px;color:#8995a5;font-size:.7rem">Sorting Center account</div>
                </div>
                <div class="sc-profile-fields">
                    <div class="sc-detail"><span>Full name</span><strong>{{ $user->name }}</strong></div>
                    <div class="sc-detail"><span>Email address</span><strong>{{ $user->email }}</strong></div>
                    <div class="sc-detail"><span>Phone number</span><strong>{{ $user->phone ?: 'Not provided' }}</strong></div>
                    <div class="sc-detail"><span>Address</span><strong>{{ $user->address ?: 'Not provided' }}</strong></div>
                    <div class="sc-detail"><span>Account role</span><strong>{{ str_replace('_', ' ', ucfirst($user->role)) }}</strong></div>
                    <div class="sc-detail"><span>Member since</span><strong>{{ $user->created_at?->format('M j, Y') ?: '—' }}</strong></div>
                </div>
            </div>
        </div>
    </section>
@endsection
