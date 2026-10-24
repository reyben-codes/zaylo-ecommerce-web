{{-- Waybill label (4 × 6 in). Rendered to PDF by dompdf (files read from disk), or as a print page in the browser when
     $web is set (then $pdfUrl links to the PDF). Expects $waybill. --}}
@php
    $web = $web ?? false;
    $asset = fn (string $path) => $web ? asset($path) : public_path($path);
    $placed = $waybill['placed'] ? \Illuminate\Support\Carbon::parse($waybill['placed'])->timezone('Asia/Manila') : null;
    $weight = $waybill['weight'] === null ? null
        : ($waybill['weight'] >= 1000 ? number_format($waybill['weight'] / 1000, 2).' kg' : $waybill['weight'].' g');
    $shownItems = $waybill['items']->take(4);
    $moreItems = $waybill['items']->count() - $shownItems->count();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Waybill {{ $waybill['tracking'] }}</title>
    <link rel="stylesheet" href="{{ $asset('css/waybill-pdf.css') }}{{ $web ? '?v='.filemtime(public_path('css/waybill-pdf.css')) : '' }}">
    @if($web)
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <script src="{{ asset('js/print.js') }}?v={{ filemtime(public_path('js/print.js')) }}" defer></script>
    @endif
</head>
<body @if($web) class="wb-web" data-auto-print @endif>
    @if($web)
        <nav class="wb-toolbar" aria-label="Waybill actions">
            <button type="button" class="wb-toolbar__button wb-toolbar__button--primary" data-print hidden><i class="fas fa-print" aria-hidden="true"></i> Print label</button>
            <a href="{{ $pdfUrl }}" class="wb-toolbar__button"><i class="fas fa-file-pdf" aria-hidden="true"></i> Save as PDF</a>
            <span class="wb-toolbar__hint">Prints on a 4 × 6 in label. On regular paper, choose "Fit to page".</span>
        </nav>
        <div class="wb-sheet">
    @endif
    <table class="wb-head">
        <tr>
            <td>
                {{-- PNG images need PHP's GD extension; without it the text wordmark is used. --}}
                @if(extension_loaded('gd'))
                    <img src="{{ $asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" class="wb-logo">
                @else
                    <span class="wb-wordmark">ZAYLO</span>
                @endif
            </td>
            <td class="wb-head__meta">
                <span class="wb-title">WAYBILL</span>
                @if($waybill['carrier'])<span class="wb-carrier">{{ $waybill['carrier'] }}</span>@endif
            </td>
        </tr>
    </table>

    {{-- Riders scan either code; the QR code reads faster on some phones. --}}
    <table class="wb-barcode">
        <tr>
            <td class="wb-barcode__bars">
                <img src="{{ $waybill['barcode'] }}" alt="">
                <p class="wb-tracking">{{ $waybill['tracking'] }}</p>
            </td>
            <td class="wb-barcode__qr">
                <img src="{{ $waybill['qr'] }}" alt="QR code for {{ $waybill['tracking'] }}">
            </td>
        </tr>
    </table>

    <table class="wb-grid">
        <tr>
            <td class="wb-cell wb-cell--half"><span class="wb-label">Order</span>{{ $waybill['order'] }}</td>
            <td class="wb-cell wb-cell--half"><span class="wb-label">Placed</span>{{ $placed?->format('M j, Y') ?? '—' }}</td>
        </tr>
    </table>

    <div class="wb-block wb-block--to">
        <span class="wb-label">Deliver to</span>
        <p class="wb-name">{{ $waybill['recipient']['name'] }}</p>
        @if($waybill['recipient']['phone'])<p class="wb-phone">{{ $waybill['recipient']['phone'] }}</p>@endif
        <p class="wb-address">{{ $waybill['recipient']['address'] ?? 'No address on file' }}</p>
        @if($waybill['area'])<p class="wb-area">Area: {{ $waybill['area'] }}</p>@endif
    </div>

    <div class="wb-block wb-block--from">
        <span class="wb-label">From</span>
        <p class="wb-from-name">{{ $waybill['sender']['name'] }}@if($waybill['sender']['phone']) · {{ $waybill['sender']['phone'] }}@endif</p>
        @if($waybill['sender']['address'])<p class="wb-from-address">{{ $waybill['sender']['address'] }}</p>@endif
    </div>

    @if($waybill['cod'] > 0)
        <div class="wb-cod">
            <span class="wb-cod__label">COLLECT ON DELIVERY</span>
            <span class="wb-cod__amount">&#8369;{{ number_format($waybill['cod'], 2) }}</span>
        </div>
    @else
        <div class="wb-cod wb-cod--paid">
            <span class="wb-cod__label">{{ strtoupper($waybill['payment']) }}</span>
            <span class="wb-cod__amount">Do not collect</span>
        </div>
    @endif

    <table class="wb-items">
        <thead>
            <tr><th>Contents</th><th class="wb-qty">Qty</th></tr>
        </thead>
        <tbody>
            @forelse($shownItems as $item)
                <tr>
                    <td>{{ \Illuminate\Support\Str::limit($item['name'], 40) }}@if($item['variant']) <span class="wb-variant">({{ $item['variant'] }})</span>@endif</td>
                    <td class="wb-qty">{{ $item['quantity'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Items not listed</td></tr>
            @endforelse
            @if($moreItems > 0)
                <tr><td colspan="2" class="wb-more">+ {{ $moreItems }} more {{ \Illuminate\Support\Str::plural('item', $moreItems) }} (see order {{ $waybill['order'] }})</td></tr>
            @endif
        </tbody>
    </table>

    <table class="wb-grid">
        <tr>
            <td class="wb-cell wb-cell--half"><span class="wb-label">Pieces</span>{{ $waybill['pieces'] }}</td>
            <td class="wb-cell wb-cell--half"><span class="wb-label">Weight</span>{{ $weight ?? 'Not set' }}</td>
        </tr>
    </table>

    @if($waybill['notes'])
        <p class="wb-notes"><strong>Buyer note:</strong> {{ \Illuminate\Support\Str::limit($waybill['notes'], 100) }}</p>
    @endif

    <table class="wb-sign">
        <tr>
            <td><span class="wb-line"></span><span class="wb-label">Received by (name &amp; signature)</span></td>
            <td class="wb-sign__date"><span class="wb-line"></span><span class="wb-label">Date</span></td>
        </tr>
    </table>

    <p class="wb-foot">Printed {{ $waybill['printed']->format('M j, Y g:i A') }} · Keep this label visible on the parcel.</p>
    @if($web)
        </div>
    @endif
</body>
</html>
