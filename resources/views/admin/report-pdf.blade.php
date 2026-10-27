{{-- Admin sales summary or commission report (rendered to PDF by dompdf; files are read from disk). --}}
@php
    $isSales = $report === 'sales';
    $percent = rtrim(rtrim(number_format($rate * 100, 2), '0'), '.');
    $shopName = isset($seller) ? $seller['name'] : null;
    $productName = isset($product) ? $product['name'] : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ZAYLO {{ $isSales ? 'sales summary' : 'commission report' }}{{ $shopName ? ' · '.$shopName : '' }}{{ $productName ? ' · '.$productName : '' }} · {{ $range['label'] }}</title>
    <link rel="stylesheet" href="{{ public_path('css/logistics-report-pdf.css') }}">
</head>
<body>
    <header class="report-head">
        <table class="report-head__table">
            <tr>
                <td>
                    @if(extension_loaded('gd'))
                        <img src="{{ public_path('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" class="report-logo-image">
                    @else
                        <p class="report-logo">ZAYLO</p>
                    @endif
                    <p class="report-eyebrow">Admin Center</p>
                    <h1>{{ $isSales ? 'Sales summary report' : 'Commission report' }}</h1>
                    <p class="report-eyebrow">{{ $shopName ? 'Seller: '.$shopName : 'All sellers' }}{{ $productName ? ' · Item: '.$productName : '' }}</p>
                </td>
                <td class="report-meta">
                    <p><strong>Period:</strong> {{ $range['label'] }}</p>
                    <p><strong>Generated:</strong> {{ $generatedAt->format('M j, Y g:i A') }} (Philippine time)</p>
                    <p><strong>By:</strong> {{ $generatedBy }}</p>
                </td>
            </tr>
        </table>
    </header>

    <table class="report-stats">
        <tr>
            <td><span>Gross</span><strong>&#8369;{{ number_format($summary['gross'], 2) }}</strong><em>Non-cancelled orders</em></td>
            <td><span>Net</span><strong>&#8369;{{ number_format($summary['net'], 2) }}</strong><em>Kept by sellers</em></td>
            <td><span>Commission</span><strong>&#8369;{{ number_format($summary['commission'], 2) }}</strong><em>{{ $percent }}% of gross</em></td>
            <td><span>Total orders</span><strong>{{ number_format($summary['orders']) }}</strong><em>{{ $summary['completedOrders'] }} completed · {{ $summary['cancelled'] }} cancelled</em></td>
        </tr>
        <tr>
            @if($isSales)
                <td><span>Average order</span><strong>&#8369;{{ number_format($summary['average'], 2) }}</strong><em>Including shipping</em></td>
                <td><span>Shipping fees</span><strong>&#8369;{{ number_format($summary['shipping'], 2) }}</strong><em>Charged to buyers</em></td>
                <td><span>Completed sales</span><strong>&#8369;{{ number_format($summary['completedSales'], 2) }}</strong><em>Delivered and finished</em></td>
                <td><span>Commission earned</span><strong>&#8369;{{ number_format($summary['commissionEarned'], 2) }}</strong><em>On completed orders</em></td>
            @else
                <td><span>Earned</span><strong>&#8369;{{ number_format($summary['commissionEarned'], 2) }}</strong><em>{{ $percent }}% of completed sales</em></td>
                <td><span>Settled</span><strong>&#8369;{{ number_format($summary['commissionSettled'], 2) }}</strong><em>Collected from sellers</em></td>
                <td><span>To collect</span><strong>&#8369;{{ number_format($summary['commissionEarned'] - $summary['commissionSettled'], 2) }}</strong><em>Earned, not settled</em></td>
                <td><span>Pending</span><strong>&#8369;{{ number_format($summary['commissionPending'], 2) }}</strong><em>Orders not finished</em></td>
            @endif
        </tr>
    </table>

    <h2 class="report-section">{{ $isSales ? 'Sales by seller' : 'Commission by seller' }}</h2>
    <table class="report-table">
        <thead>
            <tr>
                <th>Seller</th><th class="num">Orders</th><th class="num">Gross</th><th class="num">Net</th><th class="num">Commission</th>
                @if($isSales)
                    <th class="num">Share of sales</th>
                @else
                    <th class="num">Earned</th><th class="num">Settled</th><th class="num">Pending</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($bySeller as $shop)
                <tr>
                    <td>{{ $shop['seller'] }}</td>
                    <td class="num">{{ $shop['orders'] }}</td>
                    <td class="num">&#8369;{{ number_format($shop['sales'], 2) }}</td>
                    <td class="num">&#8369;{{ number_format($shop['net'], 2) }}</td>
                    <td class="num">&#8369;{{ number_format($shop['commission'], 2) }}</td>
                    @if($isSales)
                        <td class="num">{{ $summary['gross'] > 0 ? round($shop['sales'] / $summary['gross'] * 100, 1) : 0 }}%</td>
                    @else
                        <td class="num">&#8369;{{ number_format($shop['earned'] + $shop['settled'], 2) }}</td>
                        <td class="num">&#8369;{{ number_format($shop['settled'], 2) }}</td>
                        <td class="num">&#8369;{{ number_format($shop['pending'], 2) }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $isSales ? 6 : 8 }}" class="muted">No orders in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($isSales)
        <table class="report-columns">
            <tr>
                <td>
                    <h2>Best-selling products</h2>
                    <table class="report-table">
                        <thead><tr><th>Product</th><th class="num">Sold</th><th class="num">Sales</th></tr></thead>
                        <tbody>
                            @forelse($topProducts as $product)
                                <tr><td>{{ $product['name'] }}</td><td class="num">{{ $product['quantity'] }}</td><td class="num">&#8369;{{ number_format($product['amount'], 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="muted">No products sold.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
                <td>
                    <h2>Orders by status</h2>
                    <table class="report-table">
                        <thead><tr><th>Status</th><th class="num">Orders</th></tr></thead>
                        <tbody>
                            @forelse($byStatus as $status => $count)
                                <tr><td>{{ Str::headline($status) }}</td><td class="num">{{ $count }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="muted">No orders.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <h2 class="report-section">Sales per {{ $monthly ? 'month' : 'day' }}</h2>
        <table class="report-table">
            <thead><tr><th>{{ $monthly ? 'Month' : 'Day' }}</th><th class="num">Orders</th><th class="num">Sales</th></tr></thead>
            <tbody>
                @foreach($series->filter(fn ($point) => $point['orders'] > 0) as $point)
                    <tr><td>{{ $point['label'] }}</td><td class="num">{{ $point['orders'] }}</td><td class="num">&#8369;{{ number_format($point['sales'], 2) }}</td></tr>
                @endforeach
                @if($series->sum('orders') === 0)
                    <tr><td colspan="3" class="muted">No sales in this period.</td></tr>
                @endif
            </tbody>
        </table>
    @else
        <h2 class="report-section">Orders</h2>
        <table class="report-table report-table--parcels">
            <thead><tr><th>Order</th><th>Date</th><th>Seller</th><th>Status</th><th class="num">Subtotal</th><th class="num">Commission</th><th>State</th></tr></thead>
            <tbody>
                @forelse($rows->sortBy('placed') as $row)
                    <tr>
                        <td class="mono">{{ $row['number'] }}</td>
                        <td>{{ $row['placed']?->timezone('Asia/Manila')->format('M j, Y') }}</td>
                        <td>{{ $row['seller'] }}</td>
                        <td>{{ Str::headline($row['status']) }}</td>
                        <td class="num">&#8369;{{ number_format($row['subtotal'], 2) }}</td>
                        <td class="num">&#8369;{{ number_format($row['commission'], 2) }}</td>
                        <td>{{ \App\Http\Controllers\AdminFinanceController::COMMISSION_STATES[$row['commission_status']] ?? $row['commission_status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No orders in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
