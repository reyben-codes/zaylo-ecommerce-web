{{-- Printable logistics report (rendered to PDF by dompdf; files are read from disk, so paths are local). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $provider?->name ?? 'Logistics' }} report · {{ $period }}</title>
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
                    <p class="report-eyebrow">Logistics report</p>
                    <h1>{{ $provider?->name ?? 'Logistics Center' }}</h1>
                    @if(!empty($item))
                        <p style="margin:2px 0; font-size:10px; color:#4a4138;"><strong>Filtered Item:</strong> {{ $item }}</p>
                    @endif
                    @if(!empty($stage))
                        <p style="margin:2px 0; font-size:10px; color:#4a4138;"><strong>Filtered Stage:</strong> {{ ucfirst(str_replace('_', ' ', $stage)) }}</p>
                    @endif
                </td>
                <td class="report-meta">
                    <p><strong>Period:</strong> {{ $period }}</p>
                    <p><strong>Generated:</strong> {{ $generatedAt->format('M j, Y g:i A') }} (Philippine time)</p>
                    <p><strong>By:</strong> {{ $generatedBy }}</p>
                </td>
            </tr>
        </table>
    </header>

    <table class="report-stats">
        <tr>
            <td><span>Parcels</span><strong>{{ number_format($total) }}</strong><em>Handled in this period</em></td>
            <td><span>Delivered</span><strong>{{ number_format($delivered) }}</strong><em>{{ $successRate }}% success rate</em></td>
            <td><span>In progress</span><strong>{{ number_format($active) }}</strong><em>Pickup to out for delivery</em></td>
            <td><span>Failed or returned</span><strong>{{ number_format($failed) }}</strong><em>{{ $failed ? 'Needs follow-up' : 'None' }}</em></td>
        </tr>
    </table>

    <table class="report-columns">
        <tr>
            <td>
                <h2>Parcels by step</h2>
                <table class="report-table">
                    <thead><tr><th>Step</th><th class="num">Parcels</th></tr></thead>
                    <tbody>
                        @foreach($byStage as $row)
                            <tr><td>{{ $row['label'] }}</td><td class="num">{{ number_format($row['total']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td>
                <h2>Riders</h2>
                <table class="report-table">
                    <thead><tr><th>Rider</th><th class="num">Parcels</th><th class="num">Delivered</th><th class="num">Failed</th><th class="num">Success</th></tr></thead>
                    <tbody>
                        @forelse($byRider as $row)
                            <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['total'] }}</td><td class="num">{{ $row['delivered'] }}</td><td class="num">{{ $row['failed'] }}</td><td class="num">{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="muted">No parcels were assigned to riders in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <h2>Delivery areas</h2>
                <table class="report-table">
                    <thead><tr><th>Area</th><th class="num">Parcels</th><th class="num">Delivered</th><th class="num">Failed</th><th class="num">Success</th></tr></thead>
                    <tbody>
                        @forelse($byArea as $row)
                            <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['total'] }}</td><td class="num">{{ $row['delivered'] }}</td><td class="num">{{ $row['failed'] }}</td><td class="num">{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="muted">No parcels in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2 class="report-section">Parcels</h2>
    @if($total > $parcels->count())
        <p class="muted">Showing the first {{ number_format($parcels->count()) }} of {{ number_format($total) }} parcels. Narrow the dates to list the rest.</p>
    @endif
    <table class="report-table report-table--parcels">
        <thead>
            <tr>
                <th>Tracking</th>
                <th>Order</th>
                <th>Seller</th>
                <th>Deliver to</th>
                <th>Area</th>
                <th>Step</th>
                <th>Rider</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($parcels as $parcel)
                <tr>
                    <td class="mono">{{ $parcel['tracking'] }}</td>
                    <td>{{ $parcel['order'] }}</td>
                    <td>{{ $parcel['seller'] }}</td>
                    <td><strong>{{ $parcel['recipient'] }}</strong><br><span class="muted">{{ $parcel['destination'] }}</span></td>
                    <td>{{ $parcel['area'] ?: '—' }}</td>
                    <td>{{ $parcel['label'] }}</td>
                    <td>{{ $parcel['rider'] ?? '—' }}</td>
                    <td>{{ $parcel['created'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No parcels in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
