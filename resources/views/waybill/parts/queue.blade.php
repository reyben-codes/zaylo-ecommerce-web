{{-- The partner printed this for a seller without a printer: match it to the parcel marked the same. --}}
@if($waybill['queue'])
    <table class="wb-queue wb-queue--{{ $waybill['queue']['color'] }}">
        <tr>
            <td class="wb-queue__swatch">&nbsp;</td>
            <td><span class="wb-queue__mark">{{ $waybill['queue']['number'] }} · {{ $waybill['queue']['name'] }}</span><span class="wb-label">Match to the parcel marked {{ $waybill['queue']['number'] }} {{ $waybill['queue']['name'] }}@if($waybill['queue']['date']) ({{ $waybill['queue']['date'] }})@endif</span></td>
        </tr>
    </table>
@endif
