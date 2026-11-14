{{-- The next steps for one parcel. Expects $shipment, optional $riders (assign a rider when it is waiting for one) and
     optional $scanned (the tracking number just scanned on the Scan parcel page, which proves the waybill is in hand).
     Sorting suggestions come from a <datalist id="sort-areas"> on the page. --}}
@php
    $workflow = \App\Services\ShipmentWorkflow::class;
    // The rider's own steps (out for delivery, delivered, failed) are taken in the rider app, not here.
    $steps = $workflow::logisticsSteps($shipment);
    $deliveries = \App\Services\RiderDeliveries::class;
    $awaitingRider = $workflow::awaitingRider($shipment);
    $awaitingConfirmation = $workflow::awaitingPickupConfirmation($shipment);
    $awaitingSort = $workflow::awaitingSort($shipment);
    $stage = $workflow::stageOf($shipment);
    $riders = $riders ?? null;
    $couriers = $couriers ?? null;
    $tracking = $shipment->tracking_number ?? $shipment->tracking_code;
    $today = now('Asia/Manila');
    $scanned = $scanned ?? null;
    $thisParcel = (string) old('parcel') === (string) $shipment->id;
    $localPickupRiders = isset($pickupRiders)
        ? app(\App\Services\RiderProximity::class)->serving($pickupRiders, \App\Services\RiderSellerPickups::pickupArea($shipment), \App\Services\RiderSellerPickups::pickupAddress($shipment))
        : collect();
@endphp

<div class="parcel-actions">
    @if(in_array($stage, ['ready', 'confirmed'], true) && isset($pickupRiders))
        @if($localPickupRiders->isNotEmpty() && $shipment->logistics_provider_id)
            <form method="POST" action="{{ route('logistics.parcels.pickup-rider', $shipment) }}" class="parcel-inline-form">
                @csrf
                <label for="pickup-rider-{{ $shipment->id }}">Pickup rider</label>
                <select id="pickup-rider-{{ $shipment->id }}" name="rider_id" required>
                    <option value="">Choose pickup rider</option>
                    @foreach($localPickupRiders as $pickupRider)
                        <option value="{{ $pickupRider->id }}" @selected((int) $shipment->pickup_courier_id === (int) $pickupRider->user_id)>{{ $pickupRider->user->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="seller-button seller-button--small">Assign pickup rider</button>
            </form>
        @else
            <span class="seller-cell-note">{{ $shipment->logistics_provider_id ? 'No on-shift pickup riders in the seller location.' : 'Confirm this pickup before assigning a rider.' }}</span>
        @endif
    @endif
    @if($awaitingConfirmation)
        <form method="POST" action="{{ route('logistics.parcels.pickup', $shipment) }}" class="parcel-inline-form">
            @csrf
            @method('PATCH')
            <input type="hidden" name="decision" value="confirm">
            <label class="sr-only" for="pickup-date-{{ $shipment->id }}">Pickup date for parcel {{ $tracking }}</label>
            <input type="date" id="pickup-date-{{ $shipment->id }}" name="pickup_date" value="{{ $today->toDateString() }}"
                   min="{{ $today->toDateString() }}" max="{{ $today->copy()->addDays(14)->toDateString() }}" required>
            @if(isset($couriers) && $couriers->isNotEmpty())
                <label class="sr-only" for="courier-{{ $shipment->id }}">Assign courier</label>
                <select id="courier-{{ $shipment->id }}" name="courier_id">
                    <option value="">Courier (optional)</option>
                    @foreach($couriers as $c)
                        <option value="{{ $c->user->id }}">{{ $c->user->name }} ({{ $c->vehicle_type ?: 'Courier' }})</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-calendar-check" aria-hidden="true"></i> Confirm pickup</button>
        </form>
    @endif

    @if($stage === 'confirmed' && isset($couriers) && $couriers->isNotEmpty())
        <form method="POST" action="{{ route('logistics.parcels.courier', $shipment) }}" class="parcel-inline-form">
            @csrf
            <label class="sr-only" for="assign-courier-{{ $shipment->id }}">Assign courier</label>
            <select id="assign-courier-{{ $shipment->id }}" name="courier_id" required>
                <option value="">{{ $parcel['pickup_courier'] ? 'Reassign courier' : 'Assign courier' }}</option>
                @foreach($couriers as $c)
                    <option value="{{ $c->user->id }}" @selected($parcel['pickup_courier_id'] === $c->user->id)>{{ $c->user->name }} ({{ $c->vehicle_type ?: 'Courier' }})</option>
                @endforeach
            </select>
            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-truck-pickup" aria-hidden="true"></i> {{ $parcel['pickup_courier'] ? 'Reassign' : 'Assign courier' }}</button>
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

    @if($awaitingRider && ($decline = $deliveries::lastDecline($shipment)))
        <span class="seller-cell-note parcel-declined"><i class="fas fa-user-xmark" aria-hidden="true"></i> Declined by {{ $decline->user?->name ?? 'a rider' }}: {{ $deliveries::reasonLabel('declined', $decline->reason) }}@if($decline->note) ({{ $decline->note }})@endif</span>
    @endif

    @if($awaitingRider && $riders !== null)
        @if($riders->isEmpty())
            <span class="seller-cell-note">No riders on shift. Riders can take parcels once they time in at the hub. <a href="{{ route('logistics.attendance') }}">See attendance</a></span>
        @else
            <form method="POST" action="{{ route('logistics.parcels.assign', $shipment) }}" class="parcel-inline-form">
                @csrf
                <label class="sr-only" for="rider-{{ $shipment->id }}">Rider for parcel {{ $tracking }}</label>
                <select id="rider-{{ $shipment->id }}" name="rider_id" required>
                    <option value="">Choose rider</option>
                    @include('logistics.operations.partials.rider-options', ['riders' => $riders, 'area' => \App\Services\RiderProximity::areaOf($shipment), 'pin' => \App\Services\RiderProximity::pinOf($shipment)])
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
            @if($status === 'in_transit' && $shipment->pickup_scanned_at)
                <fieldset class="handover-checklist">
                    <legend>Confirm handover from {{ $shipment->pickupCourier?->name }}</legend>
                    <label><input type="checkbox" name="parcel_received" value="1" required> Parcel physically received</label>
                    <label><input type="checkbox" name="tracking_verified" value="1" required> Tracking number matches {{ $tracking }}</label>
                    <label><input type="checkbox" name="items_checked" value="1" required> Parcel and item count checked against the waybill</label>
                </fieldset>
                @foreach(['parcel_received', 'tracking_verified', 'items_checked', 'handover'] as $field)
                    @error($field)<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                @endforeach
            @endif
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="parcel" value="{{ $shipment->id }}">
            @if($status === 'picked_up')
                {{-- Collecting from the seller: the waybill's code proves the parcel is in hand. --}}
                @if($scanned)
                    <input type="hidden" name="tracking" value="{{ $scanned }}">
                @else
                    <label class="sr-only" for="pickup-code-{{ $shipment->id }}">Scan or type the waybill code of parcel {{ $tracking }}</label>
                    <input type="text" id="pickup-code-{{ $shipment->id }}" name="tracking" class="parcel-code-input" required maxlength="100"
                           autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Scan waybill code"
                           @if($thisParcel && $errors->has('tracking')) aria-invalid="true" aria-describedby="pickup-code-error-{{ $shipment->id }}" @endif>
                @endif
            @endif
            <button type="submit" class="seller-button seller-button--small{{ $danger ? ' seller-button--danger' : ($loop->first ? '' : ' seller-button--secondary') }}">{{ $label }}</button>
            @if($status === 'picked_up' && $thisParcel && $errors->has('tracking'))
                <p id="pickup-code-error-{{ $shipment->id }}" class="seller-field__error" role="alert">{{ $errors->first('tracking') }}</p>
            @endif
        </form>
    @endforeach

    @if(! $steps && ! $awaitingRider && ! $awaitingConfirmation && ! $awaitingSort)
        <span class="seller-cell-note">{{ match (true) {
            in_array($stage, ['delivered', 'returned', 'cancelled'], true) => 'Done',
            $deliveries::needsAcceptance($shipment) => 'Waiting for the rider to accept',
            default => 'With the rider',
        } }}</span>
    @endif
    @if($stage === 'failed' && $deliveries::tracksDecisions() && ($failure =$shipment->riderLogs()->where('action', 'failed')->reorder()->latest('id')->first()))
        <span class="seller-cell-note parcel-declined"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $deliveries::failureSummary($failure->reason, $failure->note) }}</span>
    @endif

    @if(\App\Support\Waybill::available($shipment))
        <a href="{{ route('logistics.parcels.waybill.print', $shipment) }}" class="seller-button seller-button--small seller-button--secondary" target="_blank" rel="noopener"
           aria-label="Print waybill for parcel {{ $tracking }} (opens in a new tab)"><i class="fas fa-print" aria-hidden="true"></i> Print waybill</a>
    @endif
</div>
