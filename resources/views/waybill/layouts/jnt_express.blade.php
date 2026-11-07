{{-- J&T Express layout: the courier's usual 100 x 150 mm label order (no logo): service band, big sort code,
     barcode, receiver over sender, COD, goods, then the sign-off. Riders scan the same barcode or QR code. --}}
<table class="jt-band">
    <tr>
        <td class="jt-band__name">J&amp;T EXPRESS</td>
        <td class="jt-band__service">EZ · STANDARD<br><span>Via {{ $waybill['carrier'] ?? 'ZAYLO logistics' }}</span></td>
    </tr>
</table>
@include('waybill.parts.queue')
<table class="jt-sort">
    <tr>
        <td class="jt-sort__code">{{ $waybill['sort_code'] }}</td>
        <td class="jt-sort__meta"><span class="wb-label">Order</span>{{ $waybill['order'] }}<br><span class="wb-label">Date</span>{{ $placed?->format('Y-m-d') ?? '—' }}</td>
    </tr>
</table>
<div class="jt-barcode">
    <img src="{{ $waybill['barcode'] }}" alt="">
    <p class="wb-tracking">{{ $waybill['tracking'] }}</p>
</div>
<table class="jt-party">
    <tr>
        <td class="jt-party__tag">RECEIVER</td>
        <td>
            <p class="wb-name">{{ $waybill['recipient']['name'] }} @if($waybill['recipient']['phone'])<span class="jt-phone">{{ $waybill['recipient']['phone'] }}</span>@endif</p>
            <p class="wb-address">{{ $waybill['recipient']['address'] ?? 'No address on file' }}</p>
        </td>
    </tr>
    <tr>
        <td class="jt-party__tag jt-party__tag--sender">SENDER</td>
        <td>
            <p class="wb-from-name">{{ $waybill['sender']['name'] }}@if($waybill['sender']['phone']) · {{ $waybill['sender']['phone'] }}@endif</p>
            @if($waybill['sender']['address'])<p class="wb-from-address">{{ $waybill['sender']['address'] }}</p>@endif
        </td>
    </tr>
</table>
<table class="jt-cod">
    <tr>
        <td class="jt-cod__label">{{ $waybill['cod'] > 0 ? 'COD' : strtoupper($waybill['payment']) }}</td>
        <td class="jt-cod__amount">{{ $waybill['cod'] > 0 ? '₱'.number_format($waybill['cod'], 2) : 'NO CASH TO COLLECT' }}</td>
        <td class="jt-cod__qr"><img src="{{ $waybill['qr'] }}" alt="QR code for {{ $waybill['tracking'] }}"></td>
    </tr>
</table>
@include('waybill.parts.items')
<table class="wb-grid">
    <tr>
        <td class="wb-cell wb-cell--half"><span class="wb-label">Pcs</span>{{ $waybill['pieces'] }}</td>
        <td class="wb-cell wb-cell--half"><span class="wb-label">Weight</span>{{ $weight ?? 'Not set' }}</td>
    </tr>
</table>
@if($waybill['notes'])<p class="wb-notes"><strong>Remarks:</strong> {{ \Illuminate\Support\Str::limit($waybill['notes'], 90) }}</p>@endif
<table class="wb-sign">
    <tr>
        <td><span class="wb-line"></span><span class="wb-label">Receiver's signature</span></td>
        <td class="wb-sign__date"><span class="wb-line"></span><span class="wb-label">Date</span></td>
    </tr>
</table>
