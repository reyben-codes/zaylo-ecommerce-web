@extends($layout, ['workspace' => auth()->user()->hasRole('admin') ? 'admin' : 'logistics'])
@section('title', 'Report an account')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}?v={{ filemtime(public_path('css/account-reports.css')) }}">
@endpush
@section('content')
<div class="account-reports-page">
    <h1>Report an account</h1>
    <p>Tell the ZAYLO admin team about misconduct, harassment, suspected fraud, or a service concern. Your report is visible to you and administrators.</p>
    @if(auth()->user()->hasRole('buyer', 'seller', 'rider', 'courier'))<p>For an order return or refund, <a href="{{ route('disputes.index') }}">use the order complaints process</a>.</p>@endif
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<ul class="account-report-errors" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <section class="account-report-card">
        <form method="POST" action="{{ route('account-reports.store') }}" class="account-report-form">
            @csrf
            @if($shop)
                <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                <p>Reporting seller: <strong>{{ $shop->name }}</strong></p>
            @else
                <label>Account or shop name<input name="reported_name" maxlength="120" value="{{ old('reported_name') }}" required></label>
                <label>Role (optional)<select name="reported_role"><option value="">Not sure</option>@foreach(\App\Http\Controllers\AdminController::ROLE_LABELS as $key => $label)<option value="{{ $key }}" @selected(old('reported_role') === $key)>{{ $label }}</option>@endforeach</select></label>
            @endif
            <label>Reason<select name="category" required><option value="">Choose a reason</option>@foreach(\App\Models\AccountReport::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label>What happened?<textarea name="details" minlength="20" maxlength="3000" rows="6" required placeholder="Describe what happened, when it happened, and any information that helps identify the account. Do not include passwords or payment PINs.">{{ old('details') }}</textarea></label>
            <label>Order reference (optional)<input name="order_reference" maxlength="100" value="{{ old('order_reference') }}"></label>
            <button class="account-report-submit" type="submit">Submit report</button>
        </form>
    </section>
    <h2>My reports</h2>
    @forelse($reports as $report)
        <article class="account-report-card">
            <h3>#{{ $report->id }} · {{ $report->reported_name }}</h3>
            <p>{{ \App\Models\AccountReport::STATUSES[$report->status] }} · {{ \App\Models\AccountReport::CATEGORIES[$report->category] }}</p>
            <p class="account-report-text">{{ $report->details }}</p>
            @if($report->resolution)<p class="account-report-text"><strong>Admin response:</strong> {{ $report->resolution }}</p>@endif
        </article>
    @empty<p>No reports submitted yet.</p>@endforelse
    {{ $reports->links() }}
</div>
@endsection
