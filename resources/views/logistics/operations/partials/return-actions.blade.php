{{-- What the logistics partner can do with a return parcel: give a pickup to a rider (the rider scans it at the
     buyer's door), then receive it at the hub, send it out and hand it to the seller (App\Services\ReturnPickups).
     Expects $case and $riders. --}}
@php
    $steps = \App\Services\ReturnPickups::logisticsSteps($case);
    $canAssign = \App\Services\ReturnPickups::canAssign($case);
    $waitingForRider = \App\Services\ReturnPickups::isPickup($case) && ($case->return_status ?: 'scheduled') === 'scheduled';
@endphp
<div class="return-actions">
    @if($canAssign)
        @if($riders->isEmpty())
            <span class="seller-cell-note">No riders available. <a href="{{ route('logistics.riders', ['status' => 'approved']) }}">Manage riders</a></span>
        @else
            <form method="POST" action="{{ route('logistics.returns.assign', $case) }}" class="parcel-inline-form">
                @csrf
                <label class="sr-only" for="return-rider-{{ $case->id }}">Rider to pick up return {{ $case->return_tracking_number }}</label>
                <select id="return-rider-{{ $case->id }}" name="rider_id" required>
                    <option value="">{{ $case->return_rider_id ? 'Change rider' : 'Choose rider' }}</option>
                    @foreach($riders as $rider)
                        <option value="{{ $rider->id }}" @selected((int) $case->return_rider_id === (int) $rider->user_id)>{{ $rider->user?->name }}{{ $rider->vehicle_type ? ' · '.$rider->vehicle_type : '' }}</option>
                    @endforeach
                </select>
                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-user-check" aria-hidden="true"></i> {{ $case->return_rider_id ? 'Reassign' : 'Assign' }}</button>
            </form>
        @endif
        <span class="seller-cell-note">{{ $case->return_rider_id ? 'The rider scans the return slip at the buyer\'s door.' : 'Give this pickup to a rider.' }}</span>
    @endif

    @foreach($steps as $step)
        <form method="POST" action="{{ route('logistics.returns.scan', $case) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="return_status" value="{{ $step }}">
            <button type="submit" class="seller-button seller-button--small">
                <i class="fas {{ ['sorted' => 'fa-boxes-packing', 'in_transit' => 'fa-truck-fast', 'received_by_seller' => 'fa-store'][$step] }}" aria-hidden="true"></i>
                {{ \App\Services\ReturnPickups::LOGISTICS_LABELS[$step] }}
            </button>
        </form>
    @endforeach

    @if(! $canAssign && empty($steps))
        <span class="seller-cell-note">{{ $case->returnReceived() ? 'Back with the seller.' : ($waitingForRider ? 'Waiting for the rider\'s scan.' : 'Nothing to do.') }}</span>
    @endif
</div>
