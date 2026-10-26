{{-- Seller sales report (rendered to PDF by dompdf; files are read from disk, so paths are local). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $storeName }} sales report · {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}</title>
    <link rel="stylesheet" href="{{ public_path('css/logistics-report-pdf.css') }}">
</head>
<body>
    <header class="report-head">
        <table class="report-head__table">
            <tr>
                <td>
                    {{-- PNG images need PHP's GD extension; without it the text wordmark is used. --}}
                    @if(extension_loaded('gd'))
                        <img src="{{ public_path('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" class="report-logo-image">
                    @else
                        <p class="report-logo">ZAYLO</p>
                    @endif
                    <p class="report-eyebrow">Seller Center · Sales report</p>
                    <h1>{{ $storeName }}</h1>
                    @if(! empty($product))
                        <p class="report-eyebrow">Item: {{ $product['name'] }}</p>
                    @endif
                </td>
                <td class="report-meta">
                    <p><strong>Period:</strong> {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}</p>
                    <p><strong>Generated:</strong> {{ $generatedAt->format('M j, Y g:i A') }} (Philippine time)</p>
                    <p><strong>By:</strong> {{ $generatedBy }}</p>
                </td>
            </tr>
        </table>
    </header>

    <table class="report-stats">
        <tr>
            <td><span>Delivered sales</span><strong>&#8369;{{ number_format($stats['sales'], 2) }}</strong><em>{{ $stats['delivered'] }} delivered {{ Str::plural('order', $stats['delivered']) }}</em></td>
            <td><span>Orders placed</span><strong>{{ number_format($stats['placed']) }}</strong><em>{{ $stats['cancelled'] }} cancelled</em></td>
            <td><span>Average order</span><strong>&#8369;{{ number_format($stats['average'], 2) }}</strong><em>&#8369;{{ number_format($stats['openValue'], 2) }} awaiting delivery</em></td>
            <td><span>Commission ({{ $commissionPercent }}%)</span><strong>&#8369;{{ number_format($stats['commission'], 2) }}</strong><em>Net &#8369;{{ number_format($stats['sales'] - $stats['commission'], 2) }}</em></td>
        </tr>
    </table>

    <table class="report-columns">
        <tr>
            <td>
                <h2>Sales per {{ $seriesMonthly ? 'month' : 'day' }}</h2>
                <table class="report-table">
                    <thead><tr><th>{{ $seriesMonthly ? 'Month' : 'Day' }}</th><th class="num">Sales</th></tr></thead>
                    <tbody>
                        @forelse($series->filter(fn ($point) => $point['amount'] > 0) as $point)
                            <tr><td>{{ $point['label'] }}</td><td class="num">&#8369;{{ number_format($point['amount'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">No delivered sales in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td>
                <h2>Sales by category</h2>
                <table class="report-table">
                    <thead><tr><th>Category</th><th class="num">Sales</th></tr></thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr><td>{{ $category['label'] }}</td><td class="num">&#8369;{{ number_format($category['amount'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">No sales yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2 class="report-section">Best-selling products</h2>
    <table class="report-table">
        <thead><tr><th>#</th><th>Product</th><th>Category</th><th class="num">Units sold</th><th class="num">Sales</th></tr></thead>
        <tbody>
            @forelse($topProducts as $index => $product)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $product['name'] }}</td>
                    <td>{{ $product['category'] }}</td>
                    <td class="num">{{ number_format($product['units']) }}</td>
                    <td class="num">&#8369;{{ number_format($product['revenue'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No products sold in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
