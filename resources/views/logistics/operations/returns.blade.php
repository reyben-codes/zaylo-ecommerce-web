@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Returns')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Returns</p>
            <h1>Returned items</h1>
            <p>Give each pickup to a rider, who scans the return slip at the buyer's door. Scan the item in when it reaches the hub, then bring it back to the seller.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('logistics.scan') }}" class="seller-button"><i class="fas fa-barcode" aria-hidden="true"></i> Scan a return</a>
        </div>
    </section>

    <section class="seller-panel">
        <nav class="workspace-tabs" aria-label="Filter returns">
            @foreach(['active' => 'In progress', 'done' => 'Back with the seller'] as $key => $label)
                <a href="{{ route('logistics.returns', $key === 'active' ? [] : ['tab' => $key]) }}" class="workspace-tab{{ $tab === $key ? ' is-active' : '' }}" @if($tab === $key) aria-current="true" @endif>
                    {{ $label }} <span>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table parcel-table">
                <thead>
                    <tr>
                        <th>Return</th>
                        <th>Collect from</th>
                        <th>Step</th>
                        <th>Rider</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                        <tr>
                            <td>
                                <a class="parcel-tracking" href="{{ route('logistics.scan', ['tracking' => $case->return_tracking_number]) }}">{{ $case->return_tracking_number }}</a>
                                <span class="seller-cell-note">Order {{ $case->order?->order_number ?? '#'.$case->order_id }} · {{ $case->category_label }}</span>
                            </td>
                            <td class="parcel-destination">
                                <strong>{{ $case->order?->recipient_name ?? $case->order?->buyer?->name ?? 'Buyer' }}</strong>
                                @if($case->return_method === 'dropoff')
                                    <span class="seller-cell-note">Buyer drops it off{{ $case->dropoff_deadline ? ' by '.$case->dropoff_deadline->format('M j') : '' }}</span>
                                @else
                                    <span class="seller-cell-note">{{ $case->order?->formattedShippingAddress() }}</span>
                                    <span class="seller-cell-note parcel-note"><i class="fas fa-calendar" aria-hidden="true"></i> {{ $case->pickup_date?->format('D, M j') ?? 'Date to confirm' }} · {{ $case->pickup_slot === 'afternoon' ? 'Afternoon' : 'Morning' }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="parcel-stage parcel-stage--{{ $case->return_status ?: 'scheduled' }}">{{ $case->returnStepLabel() }}</span>
                                @include('logistics.operations.partials.return-photos', ['case' => $case])
                            </td>
                            <td>{{ $case->returnRider?->name ?? '—' }}</td>
                            <td>@include('logistics.operations.partials.return-actions', ['case' => $case, 'riders' => $riders])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="seller-empty">
                                    <i class="fas fa-rotate-left" aria-hidden="true"></i>
                                    <strong>{{ $tab === 'done' ? 'No finished returns yet' : 'No returns to handle' }}</strong>
                                    <span>Returns appear here when a buyer sends back an item from an order you delivered.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
