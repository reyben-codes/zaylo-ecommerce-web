{{-- The next steps for one parcel. Expects $shipment and optional $riders (assign a rider when it is waiting for one).
     Sorting suggestions come from a <datalist id="sort-areas"> on the page. --}}
@php
    $workflow = \App\Services\ShipmentWorkflow::class;
    $steps = $workflow::nextSteps($shipment);
    $awaitingRider = $workflow::awaitingRider($shipment);
    $awaitingConfirmation = $workflow::awaitingPickupConfirmation($shipment);
    $awaitingSort = $workflow::awaitingSort($shipment);
    $stage = $workflow::stageOf($shipment);
    $riders = $riders ?? null;
    $tracking = $shipment->tracking_number ?? $shipment->tracking_code;
    $today = now('Asia/Manila');
@endphp

<div class="parcel-actions">
    @if($awaitingConfirmation)
        <form method="POST" action="{{ route('logistics.parcels.pickup', $shipment) }}" class="parcel-inline-form">
            @csrf
            @method('PATCH')
            <input type="hidden" name="decision" value="confirm">
            <label class="sr-only" for="pickup-date-{{ $shipment->id }}">Pickup date for parcel {{ $tracking }}</label>
            <input type="date" id="pickup-date-{{ $shipment->id }}" name="pickup_date" value="{{ $today->toDateString() }}"
                   min="{{ $today->toDateString() }}" max="{{ $today->copy()->addDays(14)->toDateString() }}" required>
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-calendar-check" aria-hidden="true"></i> Confirm pickup</button>
        </form>
    @endif

    @if($awaitingConfirmation || $stage === 'confirmed')
        <details class="review-reason parcel-decline" data-smooth-disclosure>
            <summary class="seller-button seller-button--small seller-button--secondary">Decline</summary>
            <div data-disclosure-content>
                <form method="POST" action="{{ route('logistics.parcels.pickup', $shipment) }}" class="review-reason__form"
                      data-confirm="Decline the pickup of parcel {{ $tracking }}? The seller is told your reason." data-confirm-title="Decline pickup?" data-confirm-button="Decline pickup" data-confirm-tone="danger">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="decision" value="decline">
                    <label for="decline-{{ $shipment->id }}">Reason (sent to the seller)</label>
                    <textarea id="decline-{{ $shipment->id }}" name="reason" minlength="5" maxlength="500" required placeholder="e.g. Outside our service area, or the parcel is too large for our riders."></textarea>
                    <button type="submit" class="seller-button seller-button--small seller-button--danger">Decline pickup</button>
                </form>
            </div>
        </details>
    @endif

    @if($awaitingSort)
        <form method="POST" action="{{ route('logistics.parcels.sort', $shipment) }}" class="parcel-inline-form">
            @csrf
            @method('PATCH')
            <label class="sr-only" for="area-{{ $shipment->id }}">Delivery area for parcel {{ $tracking }}</label>
            <input type="text" id="area-{{ $shipment->id }}" name="area" value="{{ $workflow::suggestedArea($shipment) }}" list="sort-areas"
                   minlength="2" maxlength="120" required placeholder="City, Province">
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-layer-group" aria-hidden="true"></i> Sort</button>
        </form>
    @endif

    @if($awaitingRider && $riders !== null)
        @if($riders->isEmpty())
            <span class="seller-cell-note">No riders available. <a href="{{ route('logistics.riders', ['status' => 'approved']) }}">Manage riders</a></span>
        @else
            <form method="POST" action="{{ route('logistics.parcels.assign', $shipment) }}" class="parcel-inline-form">
                @csrf
                <label class="sr-only" for="rider-{{ $shipment->id }}">Rider for parcel {{ $tracking }}</label>
                <select id="rider-{{ $shipment->id }}" name="rider_id" required>
                    <option value="">Choose rider</option>
                    @foreach($riders as $rider)
                        <option value="{{ $rider->id }}">{{ $rider->user?->name }}{{ $rider->vehicle_type ? ' · '.$rider->vehicle_type : '' }}</option>
                    @endforeach
                </select>
                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-user-check" aria-hidden="true"></i> Assign</button>
            </form>
        @endif
    @elseif($awaitingRider)
        <a href="{{ route('logistics.assignments', array_filter(['area' => $shipment->sort_area])) }}" class="seller-button seller-button--small seller-button--secondary"><i class="fas fa-people-arrows" aria-hidden="true"></i> Assign rider</a>
    @endif

    @foreach($steps as $status => $label)
        @php $danger = in_array($status, ['failed', 'returned'], true); @endphp
        <form method="POST" action="{{ route('logistics.parcels.advance', $shipment) }}"
              @if($danger) data-confirm="{{ $status === 'failed' ? 'Mark parcel '.$tracking.' as failed? The rider can try again or return it to the seller.' : 'Return parcel '.$tracking.' to the seller? This ends the delivery.' }}"
                  data-confirm-title="{{ $label }}?" data-confirm-button="{{ $label }}" data-confirm-tone="danger" @endif>
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $status }}">
            <button type="submit" class="seller-button seller-button--small{{ $danger ? ' seller-button--danger' : ($loop->first ? '' : ' seller-button--secondary') }}">{{ $label }}</button>
        </form>
    @endforeach

    @if(! $steps && ! $awaitingRider && ! $awaitingConfirmation && ! $awaitingSort)
        <span class="seller-cell-note">{{ in_array($stage, ['delivered', 'returned', 'cancelled'], true) ? 'Done' : 'With the rider' }}</span>
    @endif

    @if(\App\Support\Waybill::available($shipment))
        <a href="{{ route('logistics.parcels.waybill.print', $shipment) }}" class="seller-button seller-button--small seller-button--secondary" target="_blank" rel="noopener"
           aria-label="Print waybill for parcel {{ $tracking }} (opens in a new tab)"><i class="fas fa-print" aria-hidden="true"></i> Print waybill</a>
    @endif
</div>
