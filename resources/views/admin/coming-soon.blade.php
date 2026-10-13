@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Coming Soon')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>Coming soon</h1>
            <p>This section isn't connected to live marketplace data yet.</p>
        </div>
    </section>

    <section class="seller-panel">
        <div class="seller-empty">
            <i class="fas fa-person-digging" aria-hidden="true"></i>
            <strong>Not available yet</strong>
            <span>Use Approvals, Users, and Logistics for account management in the meantime.</span>
        </div>
    </section>
@endsection
