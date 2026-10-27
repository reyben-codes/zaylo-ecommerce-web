@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Scan parcel')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/parcel-scan.js') }}?v={{ filemtime(public_path('js/parcel-scan.js')) }}" defer></script>
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Sorting center</p>
            <h1>Scan parcel</h1>
            <p>Scan the waybill with your phone's camera or a barcode scanner, or type the tracking number, to see where the parcel is and move it to its next step. When collecting from a seller, scan the waybill here to mark it picked up.</p>
        </div>
    </section>

    <datalist id="sort-areas">
        @foreach($knownAreas as $known)
            <option value="{{ $known }}"></option>
        @endforeach
    </datalist>

    <section class="seller-panel">
        <form method="GET" action="{{ route('logistics.scan') }}" class="parcel-scan" role="search" data-parcel-scan-form>
            <label for="tracking" class="seller-field__label">Tracking number</label>
            <div class="parcel-scan__row">
                <i class="fas fa-barcode" aria-hidden="true"></i>
                <input type="search" id="tracking" name="tracking" value="{{ $tracking }}" placeholder="e.g. TRK-OZIMZ8VNRMDW" autocomplete="off" autofocus required maxlength="100">
                <button type="submit" class="seller-button"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Find parcel</button>
                {{-- Shown by js/parcel-scan.js wherever the browser can use a camera (built-in barcode reader, else ZXing). --}}
                <button type="button" class="seller-button seller-button--secondary" data-camera-scan hidden><i class="fas fa-camera" aria-hidden="true"></i> Use camera</button>
            </div>
        </form>

        <dialog class="camera-scan" aria-labelledby="camera-scan-title" data-camera-dialog>
            <div class="camera-scan__panel">
                <div class="camera-scan__head">
                    <h2 id="camera-scan-title" class="camera-scan__title">Scan waybill or return code</h2>
                    <div class="camera-scan__tools">
                        <button type="button" class="seller-button seller-button--secondary camera-scan__tool" data-camera-torch hidden title="Toggle flashlight">
                            <i class="fas fa-bolt" aria-hidden="true"></i> <span class="sr-only">Flashlight</span>
                        </button>
                        <button type="button" class="seller-button seller-button--secondary camera-scan__tool" data-camera-flip hidden title="Switch camera">
                            <i class="fas fa-camera-rotate" aria-hidden="true"></i> <span class="sr-only">Switch camera</span>
                        </button>
                    </div>
                </div>
                <div class="camera-scan__view">
                    <video class="camera-scan__video" playsinline muted data-camera-video></video>
                    <div class="camera-scan__reticle" aria-hidden="true">
                        <span class="camera-scan__corner camera-scan__corner--tl"></span>
                        <span class="camera-scan__corner camera-scan__corner--tr"></span>
                        <span class="camera-scan__corner camera-scan__corner--bl"></span>
                        <span class="camera-scan__corner camera-scan__corner--br"></span>
                        <div class="camera-scan__laser"></div>
                    </div>
                </div>
                <p class="camera-scan__status" role="status" data-camera-status>Point the camera at the barcode or QR code on the waybill or return ticket.</p>
                <button type="button" class="seller-button seller-button--secondary" data-camera-close>Cancel</button>
            </div>
        </dialog>

        @if($tracking !== '' && ! $shipment && ! $returnCase)
            <div class="seller-empty">
                <i class="fas fa-circle-question" aria-hidden="true"></i>
                <strong>No parcel with tracking number "{{ $tracking }}"</strong>
                <span>Check the label or return slip and scan again. Tracking numbers look like TRK-XXXXXXXXXXXX or RET-XXXXXXXX.</span>
            </div>
        @elseif($returnCase)
            <div class="parcel-result parcel-result--return">
                <div class="parcel-result__head">
                    <div>
                        <span class="seller-cell-note">Return Parcel &middot; {{ $returnCase->returnMethodName() }}</span>
                        <strong class="parcel-tracking">{{ $returnCase->return_tracking_number }}</strong>
                    </div>
                    <span class="parcel-stage parcel-stage--{{ $returnCase->return_status ?: 'scheduled' }}">
                        {{ $returnCase->returnStepLabel() }}
                    </span>
                </div>
                <dl class="workspace-detail-list parcel-result__details">
                    <div><dt>Dispute Case</dt><dd>#{{ $returnCase->id }} &middot; {{ $returnCase->reason_label }}</dd></div>
                    <div><dt>Original Order</dt><dd>{{ $returnCase->order?->order_number ?? ('#'.$returnCase->order_id) }}</dd></div>
                    <div><dt>Assigned Courier Truck</dt><dd><strong>{{ $returnCase->returnCourierName() }}</strong></dd></div>
                    <div><dt>Buyer</dt><dd>{{ $returnCase->order?->buyer?->name ?? 'Buyer' }}{{ $returnCase->order?->shipping_phone ? ' · '.$returnCase->order->shipping_phone : '' }}</dd></div>
                    <div><dt>Pickup / Return Address</dt><dd>{{ $returnCase->order?->formattedShippingAddress() ?? 'Registered buyer home address' }}</dd></div>
                    @if($returnCase->return_method === 'pickup')
                        <div><dt>Pickup Schedule</dt><dd>{{ $returnCase->pickup_date ? $returnCase->pickup_date->format('D, M j, Y') : 'Scheduled' }} ({{ $returnCase->pickup_slot === 'afternoon' ? 'Afternoon 1:00 PM - 6:00 PM' : 'Morning 9:00 AM - 1:00 PM' }})</dd></div>
                    @else
                        <div><dt>Drop-off Location</dt><dd>{{ $returnCase->dropoff_location ?? 'Authorized Logistics Hub' }} (Deadline: {{ $returnCase->dropoff_deadline ? $returnCase->dropoff_deadline->format('M j, Y') : '7 days' }})</dd></div>
                    @endif
                    <div><dt>Return To Seller</dt><dd>{{ $returnCase->order?->seller?->shop_name ?? $returnCase->order?->seller?->name ?? 'Seller Shop' }}</dd></div>
                    @if($returnCase->order && $returnCase->order->items->isNotEmpty())
                        <div><dt>Item(s)</dt><dd>{{ $returnCase->order->items->pluck('product_name')->join(', ') }}</dd></div>
                    @endif
                    @if(\App\Models\Dispute::tracksReturnRiders() && $returnCase->return_method !== 'dropoff')
                        <div><dt>Pickup rider</dt><dd>{{ $returnCase->returnRider?->name ?? 'Not assigned yet' }}{{ $returnCase->return_picked_up_at ? ' · picked up '.$returnCase->return_picked_up_at->timezone('Asia/Manila')->format('M j, g:i A') : '' }}</dd></div>
                    @endif
                </dl>
                <div class="parcel-result__actions">
                    <p class="seller-cell-note">Next step for this return</p>
                    @include('logistics.operations.partials.return-actions', ['case' => $returnCase, 'riders' => $riders])
                </div>
                {{-- The whole return so far, with the rider's and the hub's proof photos. --}}
                <div class="parcel-result__tracker">
                    <p class="seller-cell-note">Return tracking</p>
                    @include('partials.return-process', ['case' => $returnCase, 'role' => 'logistics'])
                </div>
            </div>
        @elseif($shipment)
            @php $parcel = \App\Support\ParcelView::for($shipment); @endphp
            <div class="parcel-result">
                <div class="parcel-result__head">
                    <div>
                        <span class="seller-cell-note">Parcel</span>
                        <strong class="parcel-tracking">{{ $parcel['tracking'] }}</strong>
                    </div>
                    <span class="parcel-stage parcel-stage--{{ $parcel['stage'] }}">{{ $parcel['label'] }}</span>
                </div>
                <dl class="workspace-detail-list parcel-result__details">
                    <div><dt>Order</dt><dd>{{ $parcel['order'] }}</dd></div>
                    <div><dt>Seller</dt><dd>{{ $parcel['seller'] }}</dd></div>
                    <div><dt>Deliver to</dt><dd>{{ $parcel['recipient'] }}{{ $parcel['phone'] ? ' · '.$parcel['phone'] : '' }}</dd></div>
                    <div><dt>Address</dt><dd>{{ $parcel['destination'] }}</dd></div>
                    @if($parcel['pickup_on'] && $parcel['stage'] === 'confirmed')
                        <div><dt>Pickup date</dt><dd>{{ $parcel['pickup_on']->format('D, M j, Y') }}</dd></div>
                    @endif
                    @if($parcel['area'])
                        <div><dt>Delivery area</dt><dd>{{ $parcel['area'] }}</dd></div>
                    @endif
                    <div><dt>Rider</dt><dd>{{ $parcel['rider'] ?? 'Not assigned' }}</dd></div>
                    <div><dt>Last update</dt><dd>{{ $parcel['updated']?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
                </dl>
                <div class="parcel-result__actions">
                    <p class="seller-cell-note">Next step</p>
                    @include('logistics.operations.partials.parcel-actions', ['shipment' => $shipment, 'riders' => $riders, 'scanned' => $tracking])
                </div>
            </div>
        @else
            <div class="seller-empty">
                <i class="fas fa-barcode" aria-hidden="true"></i>
                <strong>Ready to scan</strong>
                <span>The parcel's details and its next step appear here.</span>
            </div>
        @endif
    </section>
@endsection
