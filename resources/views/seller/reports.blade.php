@extends('layouts.seller')

@section('title', 'Sales Reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-reports.css') }}?v={{ filemtime(public_path('css/seller-reports.css')) }}">
@endpush

@section('content')
    @php
        $today = now('Asia/Manila');
        $presets = [
            '7 days' => [$today->copy()->subDays(6), $today],
            '30 days' => [$today->copy()->subDays(29), $today],
            '90 days' => [$today->copy()->subDays(89), $today],
            'This year' => [$today->copy()->startOfYear(), $today],
        ];
        $maxSeries = max(1, (float) $series->max('amount'));
        $maxCategory = max(1, (float) $categories->max('amount'));
    @endphp

    @include('partials.print-header', ['title' => 'Sales report · '.$from->format('M j, Y').' – '.$to->format('M j, Y')])
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Sales reports</h1>
            <p>Orders placed {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }} (Philippine time).</p>
        </div>
        <div class="seller-page-heading__actions">
            <button type="button" class="seller-button seller-button--secondary" data-print hidden><i class="fas fa-print" aria-hidden="true"></i> Print</button>
            <a href="{{ route('seller.reports.pdf', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="seller-button" target="_blank" rel="noopener"><i class="fas fa-file-pdf" aria-hidden="true"></i> PDF report</a>
        </div>
    </section>

    <section class="seller-panel report-filter">
        <form method="GET" action="{{ route('seller.reports') }}" class="seller-filter-bar">
            <div class="seller-field">
                <label for="report-from">From</label>
                <input type="date" id="report-from" name="from" value="{{ $from->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}" required>
            </div>
            <div class="seller-field">
                <label for="report-to">To</label>
                <input type="date" id="report-to" name="to" value="{{ $to->format('Y-m-d') }}" required>
            </div>
            <button type="submit" class="seller-button"><i class="fas fa-chart-line" aria-hidden="true"></i> Update report</button>
        </form>
        <nav class="report-presets" aria-label="Quick date ranges">
            @foreach($presets as $label => [$presetFrom, $presetTo])
                @php
                    $active = $presetFrom->isSameDay($from) && $presetTo->isSameDay($to);
                @endphp
                <a href="{{ route('seller.reports', ['from' => $presetFrom->format('Y-m-d'), 'to' => $presetTo->format('Y-m-d')]) }}"
                   class="report-preset{{ $active ? ' is-active' : '' }}" @if($active) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <section class="seller-stat-grid" aria-label="Report summary">
        <article class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Delivered sales</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($stats['sales'], 2) }}</strong>
            <span class="seller-stat__note">{{ $stats['delivered'] }} delivered {{ Str::plural('order', $stats['delivered']) }}</span>
        </article>
        <article class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-receipt" aria-hidden="true"></i> Orders placed</span>
            <strong class="seller-stat__value">{{ number_format($stats['placed']) }}</strong>
            <span class="seller-stat__note">{{ $stats['cancelled'] }} cancelled</span>
        </article>
        <article class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Average order</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($stats['average'], 2) }}</strong>
            <span class="seller-stat__note">&#8369;{{ number_format($stats['openValue'], 2) }} awaiting delivery</span>
        </article>
        <article class="seller-stat">
            <span class="seller-stat__label"><i class="fas fa-percent" aria-hidden="true"></i> Commission ({{ $commissionPercent }}%)</span>
            <strong class="seller-stat__value">&#8369;{{ number_format($stats['commission'], 2) }}</strong>
            <span class="seller-stat__note">Net &#8369;{{ number_format($stats['sales'] - $stats['commission'], 2) }}</span>
        </article>
    </section>

    @unless($supportsBreakdown)
        <aside class="seller-notice">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            <div>
                <strong>Detailed breakdowns are unavailable</strong>
                <p>Daily, category, and product breakdowns need the order-item records used by checkout. Totals above are still accurate.</p>
            </div>
        </aside>
    @endunless

    <div class="seller-panel-grid report-grid">
        <section class="seller-panel">
            <header class="seller-panel__header">
                <div>
                    <h2>Delivered sales</h2>
                    <p>By order date, per {{ $seriesMonthly ? 'month' : 'day' }}</p>
                </div>
            </header>
            @if($series->sum('amount') > 0)
                <div class="report-chart" role="img" aria-label="Delivered sales chart for the selected period">
                    @foreach($series as $point)
                        <div class="report-chart__column" title="{{ $point['label'] }}: ₱{{ number_format($point['amount'], 2) }}">
                            <span class="report-chart__bar" style="--bar-height: {{ round($point['amount'] / $maxSeries * 100, 2) }}%"></span>
                            <span class="report-chart__label">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <table class="sr-only">
                    <caption>Delivered sales by period</caption>
                    <tbody>
                        @foreach($series as $point)
                            <tr><th scope="row">{{ $point['label'] }}</th><td>₱{{ number_format($point['amount'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="seller-empty">
                    <i class="fas fa-chart-column" aria-hidden="true"></i>
                    <strong>No delivered sales in this period</strong>
                    <span>Sales appear once orders are marked delivered.</span>
                </div>
            @endif
        </section>

        <section class="seller-panel">
            <header class="seller-panel__header">
                <div>
                    <h2>By category</h2>
                    <p>Share of delivered sales</p>
                </div>
            </header>
            @if($categories->isNotEmpty())
                <ul class="report-categories">
                    @foreach($categories as $category)
                        <li>
                            <div class="report-categories__row">
                                <span>{{ $category['label'] }}</span>
                                <strong>&#8369;{{ number_format($category['amount'], 2) }}</strong>
                            </div>
                            <span class="report-categories__track"><span style="--bar-width: {{ round($category['amount'] / $maxCategory * 100, 2) }}%"></span></span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="seller-empty">
                    <i class="fas fa-tags" aria-hidden="true"></i>
                    <strong>No category data yet</strong>
                </div>
            @endif
        </section>
    </div>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div>
                <h2>Top products</h2>
                <p>Ranked by delivered sales in this period</p>
            </div>
        </header>
        <div class="seller-table-wrap">
            <table class="seller-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="is-numeric">Units sold</th>
                        <th class="is-numeric">Sales</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topProducts as $index => $product)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <div class="seller-product-cell">
                                    <img src="{{ $product['image'] ?: asset('images/ZAYLO_LOGO_DARK.png') }}" alt="" loading="lazy">
                                    <strong>{{ $product['name'] }}</strong>
                                </div>
                            </td>
                            <td>{{ $product['category'] }}</td>
                            <td class="is-numeric">{{ number_format($product['units']) }}</td>
                            <td class="is-numeric"><strong>&#8369;{{ number_format($product['revenue'], 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="seller-empty">
                                    <i class="fas fa-ranking-star" aria-hidden="true"></i>
                                    <strong>No product sales yet</strong>
                                    <span>Delivered orders in this period will rank your best sellers here.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
