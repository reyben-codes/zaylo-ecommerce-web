@extends('sorting-center.layout')

@section('page-title', 'Reports & analytics')

@section('module-content')
    <div class="sc-page-heading">
        <div><div class="sc-eyebrow">Operations analytics</div><h1>Reports & analytics</h1><p>Generate a date-based summary from recorded shipments and their current statuses.</p></div>
        <button class="sc-button sc-button-secondary sc-no-print" type="button" onclick="window.print()"><i class="fa-solid fa-print" aria-hidden="true"></i> Print report</button>
    </div>
    <section class="sc-card" style="margin-bottom:18px">
        <div class="sc-card-body">
            <form class="sc-toolbar sc-no-print" method="GET" action="{{ route('sorting-center.reports') }}">
                <label class="sc-field">From date<input class="sc-input" type="date" name="from" value="{{ $from }}"></label>
                <label class="sc-field">To date<input class="sc-input" type="date" name="to" value="{{ $to }}"></label>
                <button class="sc-button" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Generate report</button>
                @if ($from || $to)<a class="sc-button sc-button-secondary" href="{{ route('sorting-center.reports') }}">Clear dates</a>@endif
            </form>
            <div><div class="sc-eyebrow">Reporting period</div><strong>{{ $from ? \Illuminate\Support\Carbon::parse($from)->format('M j, Y') : 'Beginning' }} — {{ $to ? \Illuminate\Support\Carbon::parse($to)->format('M j, Y') : 'Today' }}</strong></div>
        </div>
    </section>

    <section class="sc-metrics" aria-label="Shipment report summary">
        <article class="sc-card sc-metric"><span class="sc-metric-icon"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></span><span class="sc-metric-label">Parcels received</span><strong class="sc-metric-value">{{ number_format($total) }}</strong><div class="sc-metric-note">Created in selected date range</div></article>
        <article class="sc-card sc-metric"><span class="sc-metric-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><span class="sc-metric-label">Delivered</span><strong class="sc-metric-value">{{ number_format($delivered) }}</strong><div class="sc-metric-note">Currently marked delivered</div></article>
        <article class="sc-card sc-metric"><span class="sc-metric-icon"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span><span class="sc-metric-label">In progress</span><strong class="sc-metric-value">{{ number_format($inProgress) }}</strong><div class="sc-metric-note">Assigned or on the road</div></article>
        <article class="sc-card sc-metric"><span class="sc-metric-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><span class="sc-metric-label">Delivery rate</span><strong class="sc-metric-value">{{ $total ? number_format(($delivered / $total) * 100, 1) : '0.0' }}%</strong><div class="sc-metric-note">Delivered ÷ total parcels in range</div></article>
    </section>

    <section class="sc-card">
        <div class="sc-card-header"><div><h2>Shipment status breakdown</h2><p>Counts use the latest recorded status, grouped by parcel creation date.</p></div></div>
        <div class="sc-card-body">
            @if ($byStatus->isEmpty())
                <div class="sc-empty"><i class="fa-solid fa-chart-column" aria-hidden="true"></i>No shipment data for this reporting period.</div>
            @else
                <div class="sc-report-bars">
                    @foreach ($byStatus as $row)
                        <div class="sc-report-bar">
                            <span>{{ str_replace('_', ' ', ucfirst($row->status)) }}</span>
                            <div class="sc-report-track"><div class="sc-report-fill" style="width:{{ $total ? max(2, ($row->total / $total) * 100) : 0 }}%"></div></div>
                            <strong>{{ number_format($row->total) }}</strong>
                        </div>
                    @endforeach
                </div>
            @endif
            <p style="margin-top:19px;color:#8995a5;font-size:.68rem">Report generated from current shipment records. Status changes after the selected period may affect these totals.</p>
        </div>
    </section>
@endsection
