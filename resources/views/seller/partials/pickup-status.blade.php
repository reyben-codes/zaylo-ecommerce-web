{{-- Where the parcel stands with the logistics partner, under the order status. Expects $shipment (may be null). --}}
@if($shipment)
    @php
        $stage = \App\Services\ShipmentWorkflow::stageOf($shipment);
        $partnerName = \App\Models\Shipment::tracksProvider() ? $shipment->provider?->name : null;
        $pickupOn = $shipment->pickup_scheduled_for;
        $today = now('Asia/Manila')->startOfDay();
        $day = match (true) {
            ! $pickupOn => null,
            $pickupOn->isSameDay($today) => 'today',
            $pickupOn->isSameDay($today->copy()->addDay()) => 'tomorrow',
            default => $pickupOn->format('D, M j'),
        };
    @endphp
    @if($stage === 'ready')
        <span class="pickup-status">
            <i class="far fa-clock" aria-hidden="true"></i>
            Waiting for {{ $partnerName ?? 'a logistics partner' }} to set a pickup date
        </span>
    @elseif($stage === 'confirmed')
        <span class="pickup-status pickup-status--scheduled">
            <i class="fas fa-calendar-check" aria-hidden="true"></i>
            Pickup {{ $day ? ($day === 'today' || $day === 'tomorrow' ? $day : 'on '.$day) : 'scheduled' }}@if($partnerName) · {{ $partnerName }}@endif
        </span>
    @elseif($stage === 'picked_up' && $shipment->picked_up_at)
        <span class="pickup-status">
            <i class="fas fa-truck" aria-hidden="true"></i>
            Collected {{ $shipment->picked_up_at->timezone('Asia/Manila')->format('M j, g:i A') }}@if($partnerName) by {{ $partnerName }}@endif
        </span>
    @endif
@endif
