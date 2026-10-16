@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Disputes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}?v={{ filemtime(public_path('css/disputes.css')) }}">
@endpush

@section('content')
    @php $statuses = \App\Models\Dispute::STATUSES; @endphp
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Marketplace</p>
            <h1>Complaints and disputes</h1>
            <p>Buyers file complaints from their orders. Review the details and evidence, talk to the buyer, seller and courier, then record your decision.</p>
        </div>
    </section>

    <section class="seller-panel">
        <nav class="workspace-tabs" aria-label="Filter complaints">
            <a href="{{ route('admin.disputes', array_filter(['category' => $category])) }}" class="workspace-tab{{ $status === null ? ' is-active' : '' }}" @if($status === null) aria-current="true" @endif>All <span>{{ $counts->sum() }}</span></a>
            @foreach($statuses as $key => $label)
                <a href="{{ route('admin.disputes', array_filter(['status' => $key, 'category' => $category])) }}" class="workspace-tab{{ $status === $key ? ' is-active' : '' }}" @if($status === $key) aria-current="true" @endif>{{ $label }} <span>{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.disputes') }}" class="seller-filter-bar">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <div class="seller-field">
                <label class="seller-field__label" for="category">Problem</label>
                <select id="category" name="category">
                    <option value="">Any problem</option>
                    @foreach(\App\Models\Dispute::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button>
        </form>

        <div class="seller-table-wrap">
            <table class="seller-table workspace-table">
                <thead>
                    <tr><th>Case</th><th>Order</th><th>Filed by</th><th>Status</th><th>Updated</th></tr>
                </thead>
                <tbody>
                    @forelse($disputes as $dispute)
                        <tr>
                            <td>
                                <a href="{{ route('admin.disputes.show', $dispute) }}" class="workspace-link"><strong>#{{ $dispute->id }} · {{ $dispute->category_label }}</strong></a>
                                <span class="seller-cell-note">{{ Str::limit($dispute->reason, 90) }}</span>
                            </td>
                            <td>{{ $dispute->order?->order_number ?? '#'.$dispute->order_id }}</td>
                            <td>
                                {{ $dispute->opener?->name ?? 'Deleted account' }}
                                <span class="seller-cell-note">{{ $dispute->messages_count }} {{ Str::plural('reply', $dispute->messages_count) }} · {{ count($dispute->evidence ?? []) }} {{ Str::plural('file', count($dispute->evidence ?? [])) }}</span>
                            </td>
                            <td><span class="case-pill case-pill--{{ $dispute->status }}">{{ $dispute->status_label }}</span></td>
                            <td>{{ $dispute->updated_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            <div class="seller-empty">
                                <i class="fas fa-scale-balanced" aria-hidden="true"></i>
                                <strong>No complaints {{ $status ? 'with this status' : 'yet' }}</strong>
                                <span>Buyers report problems from My Orders after an order is shipped.</span>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($disputes->hasPages())
            <div class="seller-pagination">{{ $disputes->links() }}</div>
        @endif
    </section>
@endsection
