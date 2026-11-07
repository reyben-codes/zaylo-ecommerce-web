{{-- ZAYLO Express label (the default layout). --}}
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

    @include('waybill.parts.queue')

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

    @include('waybill.parts.items')

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

