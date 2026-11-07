{{--
    Flash Express style label (100 × 150 mm thermal), following the order of Flash's own waybill: brand row with the
    black DST sorting box, the long tracking barcode first, the sorting line, the receiver large and the sender small
    side by side, the inverted COD box with the QR code, item details, then a dashed tear-off copy. Text wordmark only
    (no Flash logo). Riders scan the same barcode or QR code as on every ZAYLO label.
--}}
@php($route = \App\Support\Waybill::route($waybill['tracking']))
<table class="fx-top">
    <tr>
        <td class="fx-top__brand">
            @php($flashLogo = 'images/couriers/flash-express.png')
            @if(file_exists(public_path($flashLogo)))
                <img src="{{ $asset($flashLogo) }}" alt="Flash Express" class="fx-top__logo">
            @else
                FLASH<span>EXPRESS</span>
            @endif
        </td>
        <td class="fx-top__dst"><span>DST</span>{{ $waybill['sort_code'] }}</td>
    </tr>
</table>

<div class="fx-barcode">
    <img src="{{ $waybill['barcode'] }}" alt="">
    <p class="fx-barcode__no">{{ $waybill['tracking'] }}</p>
</div>

<table class="fx-line">
    <tr>
        <td class="fx-line__lane"><span class="wb-label">Sorting line</span>{{ $waybill['sort_code'] }}-{{ $route }}</td>
        <td><span class="wb-label">Order</span>{{ $waybill['order'] }}</td>
        <td><span class="wb-label">Date</span>{{ $placed?->format('d/m/Y') ?? '—' }}</td>
    </tr>
</table>

@include('waybill.parts.queue')

<table class="fx-party">
    <tr>
        <td class="fx-party__to">
            <span class="fx-party__tag">TO</span>
            <p class="fx-party__name">{{ $waybill['recipient']['name'] }}</p>
            @if($waybill['recipient']['phone'])<p class="fx-party__phone">{{ $waybill['recipient']['phone'] }}</p>@endif
            <p class="fx-party__address">{{ $waybill['recipient']['address'] ?? 'No address on file' }}</p>
        </td>
        <td class="fx-party__from">
            <span class="fx-party__tag fx-party__tag--light">FROM</span>
            <p>{{ $waybill['sender']['name'] }}</p>
            @if($waybill['sender']['phone'])<p>{{ $waybill['sender']['phone'] }}</p>@endif
            @if($waybill['sender']['address'])<p>{{ \Illuminate\Support\Str::limit($waybill['sender']['address'], 70) }}</p>@endif
        </td>
    </tr>
</table>

<table class="fx-cod">
    <tr>
        <td class="fx-cod__box">
            <span>{{ $waybill['cod'] > 0 ? 'COD' : strtoupper($waybill['payment']) }}</span>
            <strong>{{ $waybill['cod'] > 0 ? '₱'.number_format($waybill['cod'], 2) : '₱0.00' }}</strong>
        </td>
        <td class="fx-cod__details">
            <p><span class="wb-label">Pieces</span>{{ $waybill['pieces'] }}</p>
            <p><span class="wb-label">Weight</span>{{ $weight ?? '—' }}</p>
            <p><span class="wb-label">Carrier</span>{{ $waybill['carrier'] ?? 'Flash Express' }}</p>
        </td>
        <td class="fx-cod__qr"><img src="{{ $waybill['qr'] }}" alt="QR code for {{ $waybill['tracking'] }}"></td>
    </tr>
</table>

@include('waybill.parts.items')
@if($waybill['notes'])<p class="wb-notes"><strong>Note:</strong> {{ \Illuminate\Support\Str::limit($waybill['notes'], 80) }}</p>@endif

<table class="fx-sign">
    <tr>
        <td><span class="wb-label">Recipient signature</span></td>
        <td><span class="wb-label">Date / time</span></td>
    </tr>
</table>

{{-- Tear-off copy, as on Flash labels. --}}
<p class="wb-tear">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</p>
<table class="fx-copy">
    <tr>
        <td class="fx-copy__dst">{{ $waybill['sort_code'] }}</td>
        <td class="fx-copy__bars"><img src="{{ $waybill['barcode'] }}" alt=""><span>{{ $waybill['tracking'] }}</span></td>
        <td class="fx-copy__cod">{{ $waybill['cod'] > 0 ? '₱'.number_format($waybill['cod'], 2) : 'PAID' }}</td>
    </tr>
</table>
