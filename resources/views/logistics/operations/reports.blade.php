@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@section('content')
    @php $largest = max(1, $byStage->max('total')); @endphp
    @include('partials.print-header', ['title' => 'Logistics report'])
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Reports</h1>
            <p>Parcels handled {{ $from || $to ? 'between the dates below' : 'so far' }}, counted by the day they entered the system.</p>
        </div>
        <div class="seller-page-heading__actions">
            <button type="button" class="seller-button seller-button--secondary" data-print hidden><i class="fas fa-print" aria-hidden="true"></i> Print</button>
            <a href="{{ route('logistics.reports.export', array_filter(['from' => $from, 'to' => $to])) }}" class="seller-button seller-button--secondary"><i class="fas fa-file-pdf" aria-hidden="true"></i> Download PDF</a>
        </div>
    </section>

    <section class="seller-panel parcel-report-filter">
        <form method="GET" action="{{ route('logistics.reports') }}" class="seller-filter-bar">
            <div class="seller-field">
                <label class="seller-field__label" for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $from }}" max="{{ today()->toDateString() }}">
            </div>
            <div class="seller-field">
                <label class="seller-field__label" for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $to }}" max="{{ today()->toDateString() }}">
            </div>
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
            @if($from || $to)
                <a href="{{ route('logistics.reports') }}" class="seller-button seller-button--small seller-button--secondary">All time</a>
            @endif
        </form>
        @error('to')<p class="seller-field__error parcel-report-error" role="alert">{{ $message }}</p>@enderror
    </section>

    <section class="seller-stat-grid" aria-label="Parcel summary">
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Parcels</span>
            <strong class="seller-stat__value">{{ number_format($total) }}</strong>
            <span class="seller-stat__note">Handled in this period</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-circle-check" aria-hidden="true"></i> Delivered</span>
            <strong class="seller-stat__value">{{ number_format($delivered) }}</strong>
            <span class="seller-stat__note">{{ $successRate }}% success rate</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-route" aria-hidden="true"></i> In progress</span>
            <strong class="seller-stat__value">{{ number_format($active) }}</strong>
            <span class="seller-stat__note">From pickup to out for delivery</span>
        </div>
        <div class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Failed or returned</span>
            <strong class="seller-stat__value">{{ number_format($failed) }}</strong>
            <span class="seller-stat__note{{ $failed ? ' seller-stat__note--warning' : '' }}">{{ $failed ? 'Check Deliveries for retries' : 'None' }}</span>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>Parcels by step</h2><p>Where every parcel in this period is now.</p></div></header>
        <ul class="parcel-breakdown">
            @foreach($byStage as $key => $row)
                <li>
                    <span class="parcel-stage parcel-stage--{{ $key }}">{{ $row['label'] }}</span>
                    <span class="parcel-breakdown__bar" aria-hidden="true"><span style="--share: {{ round($row['total'] / $largest * 100) }}%"></span></span>
                    <strong>{{ number_format($row['total']) }}</strong>
                </li>
            @endforeach
        </ul>
    </section>

    <div class="seller-panel-grid parcel-report-grid">
        @foreach(['byRider' => ['Riders', 'Parcels each rider carried in this period.', 'Rider', 'No parcels were assigned to riders yet.'], 'byArea' => ['Delivery areas', 'Parcels per delivery area in this period.', 'Area', 'No parcels in this period.']] as $key => [$title, $note, $column, $none])
            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>{{ $title }}</h2><p>{{ $note }}</p></div></header>
                <div class="seller-table-wrap">
                    <table class="seller-table workspace-table">
                        <thead>
                            <tr><th>{{ $column }}</th><th>Parcels</th><th>Delivered</th><th>Failed</th><th>Success</th></tr>
                        </thead>
                        <tbody>
                            @forelse(${$key} as $row)
                                <tr>
                                    <td><strong>{{ $row['name'] }}</strong></td>
                                    <td>{{ $row['total'] }}</td>
                                    <td>{{ $row['delivered'] }}</td>
                                    <td>{{ $row['failed'] }}</td>
                                    <td>{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><p class="parcel-empty-inline">{{ $none }}</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
@endsection
