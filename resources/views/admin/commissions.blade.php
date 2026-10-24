@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Commission')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    @php $percent = rtrim(rtrim(number_format($rate * 100, 2), '0'), '.'); @endphp
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Finance</p>
            <h1>Platform commission</h1>
            <p>ZAYLO earns {{ $percent }}% of each seller's order subtotal (shipping not included) when the order is completed. Orders placed {{ $range['label'] }}.</p>
        </div>
        <div class="seller-page-heading__actions">
            <form method="POST" action="{{ route('admin.commissions.record') }}"
                  data-confirm="Record the commission for every completed order that does not have one yet?" data-confirm-title="Calculate commissions?" data-confirm-button="Calculate">
                @csrf
                <button type="submit" class="seller-button seller-button--secondary"><i class="fas fa-calculator" aria-hidden="true"></i> Calculate missing</button>
            </form>
            <a href="{{ route('admin.reports.pdf', ['report' => 'commission', 'from' => $range['from'], 'to' => $range['to']]) }}" class="seller-button"><i class="fas fa-file-pdf" aria-hidden="true"></i> Commission report</a>
        </div>
    </section>

    <section class="seller-panel admin-filter-panel">
        @include('admin.partials.date-range', ['action' => route('admin.commissions'), 'range' => $range, 'keep' => array_filter(['state' => $state])])
    </section>

    <section class="seller-stat-grid" aria-label="Commission summary">
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-percent" aria-hidden="true"></i> Earned</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['commissionEarned'], 2) }}</strong>
            <span class="seller-stat__note">On &#8369;{{ number_format($summary['completedSales'], 2) }} completed sales</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-circle-check" aria-hidden="true"></i> Settled</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['commissionSettled'], 2) }}</strong>
            <span class="seller-stat__note">Collected from sellers</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-hourglass-half" aria-hidden="true"></i> To collect</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['commissionEarned'] - $summary['commissionSettled'], 2) }}</strong>
            <span class="seller-stat__note">Earned but not settled</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-truck" aria-hidden="true"></i> Pending</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($summary['commissionPending'], 2) }}</strong>
            <span class="seller-stat__note">On orders not finished yet</span>
        </div>
    </section>

    @if($summary['commissionUnrecorded'] > 0)
        <aside class="seller-notice">
            <i class="fas fa-calculator" aria-hidden="true"></i>
            <div>
                <strong>{{ $summary['commissionUnrecorded'] }} completed {{ Str::plural('order', $summary['commissionUnrecorded']) }} without a recorded commission</strong>
                <p>The amount is calculated below. Use "Calculate missing" to save it so it can be settled.</p>
            </div>
        </aside>
    @endif

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>By seller</h2><p>Sales and commission per shop in this period.</p></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table admin-money">
                <thead><tr><th>Seller</th><th>Orders</th><th>Sales</th><th>Earned</th><th>Settled</th><th>Pending</th></tr></thead>
                <tbody>
                    @forelse($bySeller as $seller)
                        <tr>
                            <td>@if($seller['seller_id'])<a href="{{ route('admin.users.show', $seller['seller_id']) }}" class="workspace-link">{{ $seller['seller'] }}</a>@else{{ $seller['seller'] }}@endif</td>
                            <td>{{ $seller['orders'] }}</td>
                            <td>&#8369;{{ number_format($seller['sales'], 2) }}</td>
                            {{-- Earned includes what is already settled, matching the total above. --}}
                            <td>&#8369;{{ number_format($seller['earned'] + $seller['settled'], 2) }}</td>
                            <td>&#8369;{{ number_format($seller['settled'], 2) }}</td>
                            <td>&#8369;{{ number_format($seller['pending'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><p class="admin-empty-inline">No orders in this period.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Orders</h2><p>Select earned commissions you have collected and mark them settled.</p></div></header>
        <nav class="workspace-tabs" aria-label="Filter by commission state">
            <a href="{{ route('admin.commissions', ['from' => $range['from'], 'to' => $range['to']]) }}" class="workspace-tab{{ $state === null ? ' is-active' : '' }}" @if($state === null) aria-current="true" @endif>All</a>
            @foreach($states as $key => $label)
                <a href="{{ route('admin.commissions', ['state' => $key, 'from' => $range['from'], 'to' => $range['to']]) }}" class="workspace-tab{{ $state === $key ? ' is-active' : '' }}" @if($state === $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('admin.commissions.settle') }}"
              data-confirm="Mark the selected commissions as settled? Settled commissions are recorded as collected from the seller." data-confirm-if="input[name='commissions[]']:checked"
              data-confirm-title="Settle commissions?" data-confirm-button="Mark settled">
            @csrf
            <div class="seller-table-wrap">
                <table class="seller-table workspace-table admin-money">
                    <thead><tr><th><span class="sr-only">Select</span></th><th>Order</th><th>Seller</th><th>Status</th><th>Subtotal</th><th>Commission</th><th>State</th></tr></thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>
                                    @if($row['commission_id'] && $row['commission_status'] === 'earned')
                                        <input type="checkbox" name="commissions[]" value="{{ $row['commission_id'] }}" aria-label="Settle commission for order {{ $row['number'] }}">
                                    @endif
                                </td>
                                <td><strong>{{ $row['number'] }}</strong><span class="seller-cell-note">{{ $row['placed']?->timezone('Asia/Manila')->format('M j, Y') }} · {{ $row['buyer'] }}</span></td>
                                <td>{{ $row['seller'] }}</td>
                                <td>{{ Str::headline($row['status']) }}</td>
                                <td>&#8369;{{ number_format($row['subtotal'], 2) }}</td>
                                <td>&#8369;{{ number_format($row['commission'], 2) }}</td>
                                <td><span class="commission-state commission-state--{{ $row['commission_status'] }}">{{ $states[$row['commission_status']] ?? $row['commission_status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><p class="admin-empty-inline">No orders match.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('commissions')<p class="seller-field__error admin-error" role="alert">{{ $message }}</p>@enderror
            @if($rows->contains(fn ($row) => $row['commission_id'] && $row['commission_status'] === 'earned'))
                <div class="admin-form-footer">
                    <button type="submit" class="seller-button"><i class="fas fa-check-double" aria-hidden="true"></i> Mark selected as settled</button>
                </div>
            @endif
        </form>
    </section>
@endsection
