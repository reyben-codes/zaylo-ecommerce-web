@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Account')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>Account</h1>
            <p>Your administrator profile and sign-in security.</p>
        </div>
    </section>

    <div class="seller-panel-grid workspace-account-grid">
        <div class="workspace-column">
            @include('partials.account-settings-forms', ['user' => $user, 'routePrefix' => 'admin.account'])
        </div>
        <div class="workspace-column">
            <section class="seller-panel" aria-labelledby="summary-heading">
                <header class="seller-panel__header"><h2 id="summary-heading">Account summary</h2></header>
                <dl class="seller-panel__body workspace-detail-list">
                    <div><dt>Role</dt><dd>Administrator</dd></div>
                    <div><dt>Email</dt><dd>{{ $user->email_verified_at ? 'Verified' : 'Not verified' }}</dd></div>
                    <div><dt>Member since</dt><dd>{{ $user->created_at?->timezone('Asia/Manila')->format('F j, Y') ?? '—' }}</dd></div>
                </dl>
            </section>
        </div>
    </div>
@endsection
