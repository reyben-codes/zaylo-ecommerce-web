{{-- The queue mark of a waybill the logistics partner prints for the seller, e.g. "07 · BLUE". Expects $queue
     (App\Support\WaybillQueue::for) and optional $note. --}}
@if($queue)
    <span class="waybill-queue waybill-queue--{{ $queue['color'] }}">
        <span class="waybill-queue__swatch" aria-hidden="true"></span>
        <strong>{{ $queue['number'] }} · {{ $queue['name'] }}</strong>
        @if(! empty($note))<span class="waybill-queue__note">{{ $note }}</span>@endif
    </span>
@endif
