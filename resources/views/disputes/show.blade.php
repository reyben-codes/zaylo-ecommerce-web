@extends($layout)

@section('title', 'Case #'.$dispute->id)

@push('styles')
    @include('disputes.partials.styles', ['layout' => $layout])
@endpush

@section('content')
    <div class="case-page">
        <section class="seller-page-heading">
            <div>
                <p class="seller-eyebrow"><a href="{{ route('disputes.index') }}" class="workspace-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Complaints</a></p>
                <h1>Case #{{ $dispute->id }}: {{ $dispute->category_label }}</h1>
                <p>Order {{ $dispute->order?->order_number ?? '#'.$dispute->order_id }} · filed {{ $dispute->created_at?->timezone('Asia/Manila')->format('M j, Y') }} · you are the {{ Str::lower($dispute->roleOf(auth()->id())) }}</p>
            </div>
            <div class="seller-page-heading__actions">
                <span class="case-pill case-pill--{{ $dispute->status }} case-pill--large">{{ $dispute->status_label }}</span>
            </div>
        </section>

        @if(session('status'))
            <p class="case-flash" role="status">{{ session('status') }}</p>
        @endif

        @if($dispute->isClosed() && $dispute->resolution)
            <section class="seller-panel case-decision">
                <h2><i class="fas fa-gavel" aria-hidden="true"></i> Decision</h2>
                <p>{{ $dispute->resolution }}</p>
            </section>
        @endif

        @if($dispute->return_tracking_number)
            <section class="seller-panel return-tracking-card">
                <header class="seller-panel__header">
                    <div>
                        <h2><i class="fas fa-truck-ramp-box" aria-hidden="true"></i> Return &amp; Refund Tracker</h2>
                        <p>Return Tracking No: <strong>{{ $dispute->return_tracking_number }}</strong> &middot; Method: {{ $dispute->returnMethodName() }}</p>
                    </div>
                    <span class="case-pill case-pill--{{ $dispute->return_status ?: 'scheduled' }}">
                        {{ $dispute->returnStepLabel() }}
                    </span>
                </header>
                <div class="case-body return-tracking-body">
                    <div class="return-truck-info">
                        <p><strong>Courier Delivery Truck:</strong> <i class="fas fa-truck" aria-hidden="true"></i> {{ $dispute->returnCourierName() }}</p>
                        @if($dispute->return_method === 'pickup')
                            <p><strong>Pickup Schedule:</strong> {{ $dispute->pickup_date ? $dispute->pickup_date->format('l, F j, Y') : 'Scheduled' }} ({{ $dispute->pickup_slot === 'afternoon' ? 'Afternoon 1:00 PM - 6:00 PM' : 'Morning 9:00 AM - 1:00 PM' }})</p>
                            <p><strong>Pickup Address:</strong> {{ $dispute->order?->formattedShippingAddress() ?? 'Registered buyer delivery address' }}</p>
                            @if(\App\Models\Dispute::tracksReturnRiders())
                                <p><strong>Pickup rider:</strong> {{ $dispute->returnRider?->name ?? 'The logistics partner will assign a rider soon' }}{{ $dispute->return_picked_up_at ? ' · picked up '.$dispute->return_picked_up_at->timezone('Asia/Manila')->format('M j, g:i A') : '' }}</p>
                            @endif
                            <p class="seller-cell-note"><i class="fas fa-clock" aria-hidden="true"></i> <strong>Waiting time &amp; process:</strong> The rider arrives during the scheduled window and scans the barcode below when collecting the item, then brings it to the logistics hub, which returns it to the seller.</p>
                        @else
                            <p><strong>Drop-off Location:</strong> {{ $dispute->dropoff_location ?? 'Authorized Logistics Hub' }}</p>
                            <p><strong>Drop-off Deadline:</strong> {{ $dispute->dropoff_deadline ? $dispute->dropoff_deadline->format('F j, Y') : '7 days from filing' }}</p>
                            <p class="seller-cell-note"><i class="fas fa-clock" aria-hidden="true"></i> Please bring the item to the drop-off branch before the deadline and present the barcode below to the staff.</p>
                        @endif
                    </div>

                    <div class="return-barcode-box" style="text-align:center; padding:16px; background:#fff; border:1px dashed #d1c7bc; border-radius:8px; margin-top:12px;">
                        <p style="margin:0 0 8px; font-size:.85rem; font-weight:600; color:#5b4d41;">Courier Handover Barcode</p>
                        <div style="display:inline-block; max-width:100%; overflow-x:auto;">
                            {!! \App\Support\Code128::svg($dispute->return_tracking_number, 56, 2) !!}
                        </div>
                        <p style="margin:4px 0 0; font-family:monospace; font-size:.9rem; font-weight:bold; letter-spacing:1px;">{{ $dispute->return_tracking_number }}</p>
                        <small style="color:#786c5f;">Show this barcode to the courier truck driver or hub agent when surrendering the return item.</small>
                    </div>

                    <div class="return-timeline">
                        <h3>Return progress</h3>
                        @php
                            // The viewer's part in the return: the buyer, the seller, or a rider (delivery or pickup).
                            $returnRole = match ($dispute->roleOf((int) auth()->id())) {
                                'Buyer' => 'buyer',
                                'Seller' => 'seller',
                                'Courier' => 'rider',
                                default => \App\Models\Dispute::tracksReturnRiders() && (int) $dispute->return_rider_id === (int) auth()->id() ? 'rider' : 'admin',
                            };
                        @endphp
                        @include('partials.return-process', ['case' => $dispute, 'role' => $returnRole])
                    </div>
                </div>
            </section>
        @endif

        <section class="seller-panel">
            <header class="seller-panel__header"><div><h2>Complaint</h2><p>Filed by {{ (int) $dispute->opened_by === (int) auth()->id() ? 'you' : 'the buyer' }}.</p></div></header>
            <div class="case-body">
                <p class="case-text">{{ $dispute->reason }}</p>
                @if($dispute->evidence)
                    <ul class="case-files">
                        @foreach($dispute->evidence as $index => $path)
                            <li><a href="{{ route('disputes.evidence', [$dispute, $index]) }}" target="_blank" rel="noopener" class="workspace-link"><i class="fas {{ Str::endsWith(Str::lower($path), '.pdf') ? 'fa-file-pdf' : 'fa-image' }}" aria-hidden="true"></i> File {{ $index + 1 }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="seller-panel">
            <header class="seller-panel__header"><div><h2>Conversation</h2><p>The buyer, seller, courier and the ZAYLO team see these messages.</p></div></header>
            @include('disputes.partials.thread', ['dispute' => $dispute, 'replyRoute' => route('disputes.reply', $dispute)])
        </section>
    </div>
@endsection
