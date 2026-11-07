{{--
    J&T Express style label (100 × 150 mm thermal), following the order of J&T's own waybill: brand row with the
    service box, the big three-part sorting code, the waybill barcode, receiver over sender, COD / weight /
    pieces, goods and signature, then a dashed tear-off customer copy. Text wordmark only (no J&T logo).
    Riders scan the same barcode or QR code as on every ZAYLO label.
--}}
@php($route = \App\Support\Waybill::route($waybill['tracking']))
@php([$province, $city] = array_pad(explode('-', $waybill['sort_code']), 2, ''))
<table class="jt-top">
    <tr>
        <td class="jt-top__brand">
            @php($jntLogo = 'images/couriers/jnt-express.png')
            @if(file_exists(public_path($jntLogo)))
                <img src="{{ $asset($jntLogo) }}" alt="J&amp;T Express" class="jt-top__logo">
            @else
                J&amp;T<span>EXPRESS</span>
            @endif
        </td>
        <td class="jt-top__meta">
            <span class="wb-label">Order date</span>{{ $placed?->format('Y-m-d') ?? '—' }}
            <span class="wb-label">Carrier</span>{{ $waybill['carrier'] ?? 'J&T Express' }}
        </td>
        <td class="jt-top__service">EZ</td>
    </tr>
</table>

{{-- The three-part sorting code J&T prints biggest: province · city · route. --}}
<table class="jt-code">
    <tr>
        <td>{{ $province ?: 'PH' }}</td>
        <td>{{ $city ?: '—' }}</td>
        <td class="jt-code__route">{{ $route }}</td>
    </tr>
</table>

<div class="jt-barcode">
    <img src="{{ $waybill['barcode'] }}" alt="">
    <p class="jt-barcode__no">{{ $waybill['tracking'] }}</p>
</div>

@include('waybill.parts.queue')

<table class="jt-party">
    <tr>
        <td class="jt-party__tag">R<span>RECEIVER</span></td>
        <td class="jt-party__body">
            <p class="jt-party__name">{{ $waybill['recipient']['name'] }} <span>{{ $waybill['recipient']['phone'] }}</span></p>
            <p class="jt-party__address">{{ $waybill['recipient']['address'] ?? 'No address on file' }}</p>
        </td>
    </tr>
    <tr>
        <td class="jt-party__tag jt-party__tag--sender">S<span>SENDER</span></td>
        <td class="jt-party__body jt-party__body--sender">
            <p>{{ $waybill['sender']['name'] }}@if($waybill['sender']['phone']) {{ $waybill['sender']['phone'] }}@endif</p>
            @if($waybill['sender']['address'])<p>{{ $waybill['sender']['address'] }}</p>@endif
        </td>
    </tr>
</table>

<table class="jt-money">
    <tr>
        <td class="jt-money__cod">
            <span class="wb-label">{{ $waybill['cod'] > 0 ? 'COD Amount' : 'Payment' }}</span>
            <strong>{{ $waybill['cod'] > 0 ? 'PHP '.number_format($waybill['cod'], 2) : strtoupper($waybill['payment']) }}</strong>
        </td>
        <td><span class="wb-label">Weight</span>{{ $weight ?? '—' }}</td>
        <td><span class="wb-label">Pcs</span>{{ $waybill['pieces'] }}</td>
        <td class="jt-money__qr" rowspan="2"><img src="{{ $waybill['qr'] }}" alt="QR code for {{ $waybill['tracking'] }}"></td>
    </tr>
    <tr>
        <td colspan="3" class="jt-money__goods">
            <span class="wb-label">Goods</span>
            {{ $shownItems->map(fn ($item) => \Illuminate\Support\Str::limit($item['name'], 26).' ×'.$item['quantity'])->join(', ') }}@if($moreItems > 0), +{{ $moreItems }} more @endif
        </td>
    </tr>
</table>

<table class="jt-foot">
    <tr>
        <td><span class="wb-label">Order No.</span>{{ $waybill['order'] }}</td>
        <td><span class="wb-label">Remarks</span>{{ $waybill['notes'] ? \Illuminate\Support\Str::limit($waybill['notes'], 40) : '—' }}</td>
    </tr>
    <tr>
        <td class="jt-foot__sign"><span class="wb-label">Receiver signature</span></td>
        <td class="jt-foot__sign"><span class="wb-label">Date received</span></td>
    </tr>
</table>

{{-- Tear-off customer copy, as on J&T labels. --}}
<p class="wb-tear">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</p>
<table class="jt-copy">
    <tr>
        <td class="jt-copy__brand">
            @if(file_exists(public_path($jntLogo)))
                <img src="{{ $asset($jntLogo) }}" alt="J&amp;T Express" class="jt-copy__logo">
            @else
                J&amp;T<span>EXPRESS</span>
            @endif
        </td>
        <td class="jt-copy__bars"><img src="{{ $waybill['barcode'] }}" alt=""><span>{{ $waybill['tracking'] }}</span></td>
    </tr>
    <tr>
        <td colspan="2" class="jt-copy__info">{{ $waybill['recipient']['name'] }} · {{ $waybill['sort_code'] }} {{ $route }} · {{ $waybill['cod'] > 0 ? 'COD PHP '.number_format($waybill['cod'], 2) : 'No COD' }}</td>
    </tr>
</table>
