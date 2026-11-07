{{-- Flash Express layout: the courier's usual label order (no logo): big barcode first, destination code band,
     receiver and sender side by side, COD box with the QR code, then contents. --}}
<table class="fx-head">
    <tr>
        <td class="fx-head__name">FLASH EXPRESS</td>
        <td class="fx-head__meta">{{ $waybill['carrier'] ?? 'ZAYLO logistics' }}<br>{{ $placed?->format('d/m/Y') ?? '' }}</td>
    </tr>
</table>
<div class="fx-barcode">
    <img src="{{ $waybill['barcode'] }}" alt="">
    <p class="wb-tracking">{{ $waybill['tracking'] }}</p>
</div>
<table class="fx-dst">
    <tr>
        <td class="fx-dst__label">DST</td>
        <td class="fx-dst__code">{{ $waybill['sort_code'] }}</td>
        <td class="fx-dst__order"><span class="wb-label">Order</span>{{ $waybill['order'] }}</td>
    </tr>
</table>
@include('waybill.parts.queue')
<table class="fx-party">
    <tr>
        <td class="fx-party__cell fx-party__cell--to">
            <span class="wb-label">To</span>
            <p class="wb-name">{{ $waybill['recipient']['name'] }}</p>
            @if($waybill['recipient']['phone'])<p class="wb-phone">{{ $waybill['recipient']['phone'] }}</p>@endif
            <p class="wb-address">{{ $waybill['recipient']['address'] ?? 'No address on file' }}</p>
        </td>
        <td class="fx-party__cell">
            <span class="wb-label">From</span>
            <p class="wb-from-name">{{ $waybill['sender']['name'] }}</p>
            @if($waybill['sender']['phone'])<p class="wb-from-address">{{ $waybill['sender']['phone'] }}</p>@endif
            @if($waybill['sender']['address'])<p class="wb-from-address">{{ $waybill['sender']['address'] }}</p>@endif
        </td>
    </tr>
</table>
<table class="fx-cod">
    <tr>
        <td>
            <span class="fx-cod__label">{{ $waybill['cod'] > 0 ? 'COD AMOUNT' : strtoupper($waybill['payment']) }}</span>
            <span class="fx-cod__amount">{{ $waybill['cod'] > 0 ? '₱'.number_format($waybill['cod'], 2) : '₱0.00' }}</span>
            <span class="wb-label">{{ $waybill['pieces'] }} pc(s) · {{ $weight ?? 'weight not set' }}</span>
        </td>
        <td class="fx-cod__qr"><img src="{{ $waybill['qr'] }}" alt="QR code for {{ $waybill['tracking'] }}"></td>
    </tr>
</table>
@include('waybill.parts.items')
@if($waybill['notes'])<p class="wb-notes"><strong>Note:</strong> {{ \Illuminate\Support\Str::limit($waybill['notes'], 90) }}</p>@endif
<table class="wb-sign">
    <tr>
        <td><span class="wb-line"></span><span class="wb-label">Received by</span></td>
        <td class="wb-sign__date"><span class="wb-line"></span><span class="wb-label">Date</span></td>
    </tr>
</table>
